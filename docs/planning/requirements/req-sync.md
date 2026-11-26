---
id: "REQ-SYNC"
title: "Refresh independent workout sources"
---

Refresh Garmin and Hevy on authenticated app open or reload. Keep Make delivering Strava activities and edits. Direct Concept2 access is deferred.

## Acceptance criteria

- Each source recovers from retries and failed or expired credentials without advancing its cursor before durable writes. Connector status shows the last successful sync.
