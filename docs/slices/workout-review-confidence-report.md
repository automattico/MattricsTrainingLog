# Improve duplicate review confidence and duration

Stationary workouts and weight training with zero recorded distance use their positive timer for dashboard duration. The comparison still shows the original moving, timer, and elapsed values.

Long overlaps need corroboration from distance or sport and duration before becoming suggestions. Close starts alone remain available under All suggestions. A unique strong automatic match survives weaker competitors. Competing strong matches and explicit Keep separate decisions still prevent an automatic join.

The review list groups suggestions by the two workouts involved and sorts by match evidence before measurement size. Large differences requires corroborated match evidence. The comparison identifies recordings that are already linked and uses the same numerical thresholds for warnings and priority. Sport differences still appear visibly.

The live local-history preview showed 19 unresolved comparisons, including four prioritized comparisons, without any review writes. Before the change it showed 25 recording-pair suggestions, of which 23 represented distinct workout decisions. Nine suggestions represented eight priority decisions. Chrome verified the history display and synthetic merge, source change, separation, deferral and undo. Disposable Postgres verified weak and strong competitors, stationary timer duration, and the Keep separate override. Refresh dashboard parity before production approval because duration rules have changed.

[Next slice](next-workout-review-confidence-prompt.md)
