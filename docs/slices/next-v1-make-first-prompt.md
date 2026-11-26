# Decide the Make-first transition

Read AGENTS.md, the architecture brief, Codex framework, implementation progress, the v1 runtime/recovery report, the Strava sync architecture and the transition decisions in the implementation plan.

Confirm the intended first cutover with the owner. If Make continues fetching Strava, identify the permitted Postgres destination and retention, workout upsert identity and replacement for SyncState!B2. Specify when the cursor commits and how failed and late records replay. Keep the Sheet available for rollback. Rehearse with synthetic data and a separate local database. Leave Concept2 deferred and the direct-provider prototype disabled.

Privately attribute the material four-week parity differences and test a paired restore containing nonempty Hevy template assignments. Preserve the existing JSON catalog. Obtain explicit approval for any hosted write, deployment, production read switch or retirement. Update the backlog and reports and run repository, Postgres and planning gates.
