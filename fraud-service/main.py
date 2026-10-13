import os
import joblib
import numpy as np
from typing import List, Optional
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field
from rapidfuzz import fuzz

from train import train_default_model, MODEL_FILE

app = FastAPI(
    title="AcademIQ Fraud Detection Microservice",
    version="1.0.0",
    description="Fuzzy duplicate string analysis and IsolationForest anomaly scoring"
)

# Global model reference
model = None

@app.on_event("startup")
def startup_event():
    global model
    if not os.path.exists(MODEL_FILE):
        print("[Startup] model.joblib not found. Generating default IsolationForest model...")
        model = train_default_model(MODEL_FILE)
    else:
        try:
            model = joblib.load(MODEL_FILE)
            print(f"[Startup] Loaded trained IsolationForest model from {MODEL_FILE}")
        except Exception as e:
            print(f"[Startup] Error loading {MODEL_FILE}: {e}. Retraining default...")
            model = train_default_model(MODEL_FILE)

# Pydantic Schemas
class Candidate(BaseModel):
    name: str
    roll_no: Optional[str] = None
    issuer_id: Optional[str] = None
    passing_year: Optional[int] = None
    department: Optional[str] = None

class ExistingRecord(BaseModel):
    document_id: str
    name: str
    roll_no: Optional[str] = None

class FuzzyCheckRequest(BaseModel):
    candidate: Candidate
    existing: List[ExistingRecord]
    threshold: float = Field(default=85.0, ge=0.0, le=100.0)

class FuzzyMatchResult(BaseModel):
    document_id: str
    score: float
    reason: str

class AnomalyFeatures(BaseModel):
    request_count_10m: int
    distinct_ids_10m: int
    not_found_ratio: float
    tampered_ratio: float
    hour_of_day: int

class AnomalyScoreResponse(BaseModel):
    score: float
    is_anomaly: bool
    engine: str = "isolation_forest"

@app.get("/health")
def health_check():
    return {
        "status": "ok",
        "service": "AcademIQ Fraud Detection Service",
        "model_loaded": model is not None,
    }

@app.post("/fuzzy-check", response_model=List[FuzzyMatchResult])
def fuzzy_check(payload: FuzzyCheckRequest):
    matches = []
    cand_name_clean = payload.candidate.name.strip().lower()
    cand_roll = str(payload.candidate.roll_no).strip() if payload.candidate.roll_no else None

    for rec in payload.existing:
        rec_name_clean = rec.name.strip().lower()
        rec_roll = str(rec.roll_no).strip() if rec.roll_no else None

        # Check 1: Duplicate Roll (same roll number, but different recipient name)
        if cand_roll and rec_roll and cand_roll == rec_roll:
            if cand_name_clean != rec_name_clean:
                matches.append(FuzzyMatchResult(
                    document_id=rec.document_id,
                    score=1.0,
                    reason="duplicate_roll"
                ))
                continue

        # Check 2: Near Duplicate Name (rapidfuzz ratio >= threshold and < 100)
        similarity = fuzz.ratio(cand_name_clean, rec_name_clean)
        if similarity >= payload.threshold and similarity < 100.0:
            matches.append(FuzzyMatchResult(
                document_id=rec.document_id,
                score=round(float(similarity), 2),
                reason="near_duplicate_name"
            ))

    return matches

@app.post("/anomaly-score", response_model=AnomalyScoreResponse)
def anomaly_score(features: AnomalyFeatures):
    global model
    if model is None:
        if os.path.exists(MODEL_FILE):
            model = joblib.load(MODEL_FILE)
        else:
            model = train_default_model(MODEL_FILE)

    X = np.array([[
        features.request_count_10m,
        features.distinct_ids_10m,
        features.not_found_ratio,
        features.tampered_ratio,
        features.hour_of_day
    ]])

    pred = model.predict(X)[0]  # -1 = anomaly, 1 = normal
    dec = model.decision_function(X)[0]

    # Convert decision score to normalized anomaly score: negative decision = high anomaly
    # Decision scores typically range from -0.3 (anomaly) to +0.2 (normal)
    normalized_score = float(np.clip(0.5 - (dec * 1.5), 0.0, 1.0))
    is_anomaly = bool(pred == -1)

    return AnomalyScoreResponse(
        score=round(normalized_score, 4),
        is_anomaly=is_anomaly,
        engine="isolation_forest"
    )
