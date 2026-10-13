import os
import joblib
import numpy as np
import pandas as pd
from sklearn.ensemble import IsolationForest

MODEL_FILE = os.path.join(os.path.dirname(__file__), "model.joblib")

def train_default_model(save_path: str = MODEL_FILE):
    """
    Train an IsolationForest model on normal verification traffic patterns.
    Features: [request_count_10m, distinct_ids_10m, not_found_ratio, tampered_ratio, hour_of_day]
    """
    np.random.seed(42)
    n_samples = 3000

    # Synthetic normal traffic:
    # - Most users make 1 to 15 requests in 10 minutes
    # - Low not_found ratio (< 0.15)
    # - Very low tampered ratio (< 0.05)
    # - Regular operating hours (8 to 22)
    normal_request_count = np.random.geometric(p=0.3, size=n_samples) + np.random.randint(1, 5, size=n_samples)
    normal_distinct_ids = np.clip(normal_request_count - np.random.randint(0, 3, size=n_samples), 1, None)
    normal_not_found = np.random.beta(a=0.5, b=10, size=n_samples)
    normal_tampered = np.random.beta(a=0.2, b=15, size=n_samples)
    probs = np.array([
        0.01, 0.01, 0.005, 0.005, 0.005, 0.01, 0.02, 0.04,
        0.07, 0.09, 0.09, 0.08, 0.08, 0.08, 0.08, 0.07,
        0.06, 0.06, 0.05, 0.04, 0.03, 0.02, 0.015, 0.01
    ])
    probs = probs / np.sum(probs)
    normal_hours = np.random.choice(range(24), size=n_samples, p=probs)

    X_normal = np.column_stack([
        normal_request_count,
        normal_distinct_ids,
        normal_not_found,
        normal_tampered,
        normal_hours
    ])

    model = IsolationForest(
        n_estimators=150,
        max_samples=256,
        contamination=0.03,
        random_state=42
    )
    model.fit(X_normal)

    joblib.dump(model, save_path)
    print(f"[AcademIQ Fraud Service] IsolationForest model trained and saved to {save_path}")
    return model

if __name__ == "__main__":
    train_default_model()
