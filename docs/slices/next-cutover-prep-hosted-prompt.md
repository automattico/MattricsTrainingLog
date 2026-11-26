# Authorize and rehearse hosted cutover operations

Use the [deploy runbook](../../DEPLOY.md), [database runner guide](../database-environments.md), and [cutover checklist](../planning/milestones/ms-v1-0.md).

After approval for the temporary hosted path, confirm that the app uses the private CA and rejects an incorrect hostname. Test FIT decoding and Garmin and Hevy requests under the host's budget. After separate approval for the code deploy, deploy the reviewed commit while Sheets remains active and verify the authenticated dashboard. Keep prior code and read configuration available for rollback.

Before requesting approval for the read switch, prove older edits and late arrivals through the approved Strava delivery path, rehearse the paired catalog promotion on the approved host path, and rerun parity and rollback with the production-bound dataset. Record the evidence in the planning checks.
