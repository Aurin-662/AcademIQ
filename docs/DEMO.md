# Demo walkthrough (fictional data)

Reset and seed (local only; `--fresh` wipes ALL data in the dev database, issuer keys and stored reports):

```sh
docker compose run --rm app php artisan academiq:demo --fresh --force
docker compose run --rm app php artisan zkp:register credential 1   # also done by academiq:demo
```

Every demo account uses the password `demo-password-123`.

| Role | Email |
|---|---|
| Admin and authorized issuer | admin@demo.academiq.test |
| Issuer | issuer@demo.academiq.test |
| Employer | employer@demo.academiq.test |
| Police verifier (demo, not official) | police@demo.academiq.test |
| Referee | referee@demo.academiq.test |
| Previous employer | previous@demo.academiq.test |
| Students (refs `KUET-DEMO-001`, `KUET-DEMO-002`) | student1@demo.academiq.test, student2@demo.academiq.test |

## Steps (this exact flow was run end to end in a real browser)

1. **Student wallet.** Sign in as student1, open Wallet, choose a passphrase (12+ characters) and create the wallet. The browser generates the delivery key; the passphrase never leaves the device. Sign out.
2. **Issue.** Sign in as admin, Credentials > Issue credential: student reference `KUET-DEMO-001`, CGPA `3.52`, any programme/degree. Sign out.
3. **Receive.** Sign in as student1, open Wallet, unlock. The credential package is decrypted and stored locally (encrypted). The server keeps only commitment and status.
4. **Request.** Sign in as employer, New request: applicant `KUET-DEMO-001`, academic claim "CGPA at least 3.00", tick Police verification (give a lawful basis) and Reference check (position). Submit.
5. **Consent.** As student1 the Dashboard shows the request. Review exactly what each check shares, approve (untick items to decline them).
6. **Prove.** From the request page choose Generate proof, unlock, press Generate. The proof is built in the browser; only the proof and six public values are sent. Result: TRUE.
7. **Background.** Sign in as police: open the task, choose Clear, submit. Sign in as referee: choose Recommend, submit.
8. **Report.** As employer open the request: status Completed, report ready. Download the PDF (the download is logged and shown in the access history). The PDF contains outcomes only.
9. **Audit.** As admin, Audit log: filter group `report`, `proof`, `consent`. Request log shows scope and consent.

Rejection checks to try: withdraw consent before proving; revoke the credential (Admin > Credentials) before proving (result FALSE); submit a second proof for the same request (rejected).

## Verify a report

Open `/verify-report` and enter the report ID and authenticity code printed on the PDF.
