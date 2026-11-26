<?php
declare(strict_types=1);

require_once __DIR__ . '/foundation-read.php';

function workout_review_available(array $ctx): bool
{
    return $ctx['linkedWorkouts'] && (mattrics_load_config()['workout_review_enabled'] ?? true)
        && $ctx['pdo']->query("SELECT to_regclass('mattrics.workout_reviews') IS NOT NULL")->fetchColumn();
}

function workout_review_query(array $ctx, string $sql): array
{
    $statement = $ctx['pdo']->prepare($sql);
    $statement->execute(['user' => $ctx['userId']]);
    return $statement->fetchAll();
}

/** Only scalar display fields leave the database; no recording tracks or raw exports. */
function workout_review_sources(array $ctx): array
{
    $rows = workout_review_query($ctx, <<<'SQL'
SELECT r.id, l.activity_id AS base, l.link_kind AS kind, d.source_kind AS provider,
 r.started_at AS start, r.ended_at AS finish, r.import_batch_id AS batch,
 r.raw_payload->>'title' AS title, r.raw_payload->>'sport' AS sport,
 coalesce(r.raw_payload#>>'{archive_evidence,source_activity_id}',r.raw_payload#>>'{archive_evidence,garmin_summary,activityId}',
 r.raw_payload->>'direct_activity_id',r.external_id) AS external,
 r.raw_payload#>>'{session,total_distance}' AS meters, r.raw_payload#>>'{session,total_elapsed_time}' AS elapsed,
 r.raw_payload#>>'{session,total_timer_time}' AS timer, r.raw_payload#>>'{session,avg_heart_rate}' AS hr,
 r.raw_payload#>>'{session,max_heart_rate}' AS max_hr, r.raw_payload#>>'{session,total_ascent}' AS ascent,
 r.raw_payload#>>'{archive_evidence,garmin_summary,movingDuration}' AS garmin_moving_ms,
 r.raw_payload#>>'{session,total_moving_time}' AS fit_moving,
 r.raw_payload#>>'{session,avg_cadence}' AS cadence,
 r.raw_payload->>'description' AS description, r.raw_payload->>'device_name' AS device,
 (r.raw_payload->'archive_evidence') - 'garmin_summary' - 'locator' - 'original' AS evidence,
 r.raw_payload->>'duration_minutes' AS hevy_minutes,r.raw_payload->>'duration_seconds' AS hevy_seconds
FROM mattrics.mattrics_source_records r
JOIN mattrics.mattrics_activity_source_links l ON l.source_record_id=r.id
JOIN mattrics.mattrics_data_sources d ON d.id=r.data_source_id
WHERE r.user_id=:user ORDER BY r.started_at,r.id
SQL);
    $result = [];
    foreach ($rows as $row) {
        $ev = json_decode($row['evidence'] ?? 'null', true) ?? [];
        unset($row['evidence']);
        $number = static fn($value) => is_numeric($value) && (float)$value >= 0 ? (float)$value : null;
        foreach (['meters','elapsed','timer','hr','max_hr','ascent'] as $field) {
            $row[$field] = $number($row[$field]);
        }
        $row['rawElapsed'] = $row['elapsed'];
        $row['recordedKm'] = $row['meters'] === null ? null : $row['meters'] / 1000;
        $row['km'] = $row['recordedKm'];
        $row['moving'] = $number($row['fit_moving']);
        if ($row['provider'] === 'garmin' && ($ev['session_count'] ?? 1) === 1 && $number($row['garmin_moving_ms']) !== null) {
            $row['moving'] = (float)$row['garmin_moving_ms'] / 1000;
        }
        $row['edited'] = false;
        $row['start'] = mattrics_foundation_read_iso_or_null($row['start']);
        $row['finish'] = mattrics_foundation_read_iso_or_null($row['finish']);
        $row['from'] = strtotime($row['start']);
        $row['to'] = $row['finish'] ? strtotime($row['finish']) : null;
        $row['selectedStart'] = $row['start'];
        $row['selectedFinish'] = $row['finish'];
        if ($row['provider'] === 'hevy') {
            $row['sport'] = 'WeightTraining';
            $row['elapsed'] = $row['to'] === null ? $number($row['hevy_seconds']) : $row['to'] - $row['from'];
            if ($row['elapsed'] === null && is_numeric($row['hevy_minutes'])) $row['elapsed'] = 60 * (float)$row['hevy_minutes'];
        }
        if ($row['provider'] === 'strava' && ($ev['session_count'] ?? 1) === 1) {
            $row['moving'] = $number($ev['moving_time'] ?? null);
            $display = $number($ev['distance_columns'][0] ?? null);
            $meters = $number($ev['distance_columns'][1] ?? null);
            if ($display !== null && $meters !== null && abs($display * 1000 - $meters) <= max(20, $meters * .02)) {
                $row['km'] = $meters / 1000;
            }
            $row['elapsed'] = $number($ev['elapsed_columns'][0] ?? null) ?? $row['elapsed'];
            $row['edited'] = ($row['recordedKm'] !== null && abs($row['km'] - $row['recordedKm']) > .02)
                || ($row['rawElapsed'] !== null && abs($row['elapsed'] - $row['rawElapsed']) > 2);
            $indexStart = DateTimeImmutable::createFromFormat('!M j, Y, g:i:s A', (string)($ev['index_datetime'] ?? ''), new DateTimeZone('UTC'));
            // Use a changed start only if the index interval fits inside the recorded session.
            if ($indexStart && $row['elapsed'] !== null && $indexStart->getTimestamp() > $row['from'] + 1
                && $row['to'] !== null && $indexStart->getTimestamp() + $row['elapsed'] <= $row['to'] + 2) {
                $row['edited'] = true;
                $row['selectedStart'] = $indexStart->format('c');
            }
            if ($row['edited']) {
                $row['selectedFinish'] = $row['elapsed'] === null ? null :
                    gmdate('c', strtotime($row['selectedStart']) + (int)round($row['elapsed']));
                // An edited summary does not establish the start/end of the retained track.
                foreach (['hr','max_hr','ascent','timer'] as $field) $row[$field] = null;
            }
        }
        $stationary = $row['km'] === 0.0 && in_array($row['sport'], ['Workout', 'WeightTraining'], true);
        if ($stationary && $row['timer'] > 0) {
            $row['label'] = 'Timer';
            $row['seconds'] = $row['timer'];
        } else {
            $row['label'] = $row['moving'] !== null ? 'Moving' : ($row['timer'] !== null ? 'Timer' : 'Elapsed');
            $row['seconds'] = $row['moving'] ?? $row['timer'] ?? $row['elapsed'];
        }
        $id = $row['external'];
        $row['url'] = match ($row['provider']) {
            'garmin' => ctype_digit($id) ? 'https://connect.garmin.com/modern/activity/' . $id : null,
            'strava' => ctype_digit($id) ? 'https://www.strava.com/activities/' . $id : null,
            'hevy' => preg_match('/^[a-f0-9-]{36}$/iD', $id) ? 'https://hevy.com/workout/' . $id : null,
            default => null,
        };
        $result[$row['id']] = $row;
    }
    return $result;
}

function workout_review_key(string $a, string $b): string
{
    return $a < $b ? "$a/$b" : "$b/$a";
}

function workout_review_default(array $ids, array $sources): string
{
    foreach (['hevy','strava','garmin'] as $provider) {
        foreach ($ids as $id) if ($sources[$id]['provider'] === $provider) return $id;
    }
    return $ids[0];
}

/** Rebuild logical groups from immutable source links and the ordered review log.
 * Unlike moving children or overwriting an activity, this also makes undo lossless. */
function workout_review_state(array $ctx): array
{
    $sources = workout_review_sources($ctx);
    $events = workout_review_query($ctx, "SELECT revision,action,array_to_json(left_sources) AS lhs,
        array_to_json(right_sources) AS rhs,preferred,undone FROM mattrics.workout_reviews WHERE user_id=:user ORDER BY revision");
    $legacy = workout_review_query($ctx, "SELECT garmin_record_id,strava_record_id,decision FROM
        mattrics.mattrics_archive_match_review WHERE user_id=:user AND decision IS NOT NULL ORDER BY garmin_record_id,strava_record_id");
    $blocked = $later = $prefs = $roots = $members = $bases = [];
    $last = $lastPair = null;
    foreach ($legacy as $pair) {
        if (in_array($pair['decision'], ['split','keep_separate'], true)) {
            $blocked[workout_review_key($pair['garmin_record_id'],$pair['strava_record_id'])] = true;
        }
    }
    foreach ($events as &$e) {
        $e['left'] = json_decode($e['lhs'],true);
        $e['right'] = json_decode($e['rhs'],true);
        if ($e['undone']) continue;
        $last = $e['revision'];
        $lastPair = [$e['left'][0], $e['right'][0] ?? $e['left'][1]];
        if ($e['action'] === 'later') {
            [$a, $b] = $lastPair;
            $later[workout_review_key($a, $b)] = [$a, $b];
        }
        foreach ($e['left'] as $a) foreach ($e['right'] as $b) {
            $key = workout_review_key($a,$b);
            if ($e['action'] === 'separate') {
                $blocked[$key] = true;
                unset($later[$key]);
            }
            if ($e['action'] === 'link') unset($blocked[$key],$later[$key]);
        }
    }
    unset($e);
    foreach ($sources as $id=>$r) {
        $roots[$id] = $id;
        $members[$id] = [$id];
        $bases[$r['base']][] = $id;
    }
    $canJoin = static function ($a,$b) use (&$roots,&$members,&$blocked): bool {
        foreach ($members[$roots[$a]] as $i) foreach ($members[$roots[$b]] as $j) {
            if (isset($blocked[workout_review_key($i,$j)])) return false;
        }
        return true;
    };
    $join = static function ($a,$b) use (&$roots,&$members,$canJoin): void {
        $a = $roots[$a]; $b = $roots[$b];
        if ($a === $b || !$canJoin($a,$b)) return;
        foreach ($members[$b] as $id) {
            $roots[$id]=$a;
            $members[$a][]=$id;
        }
        unset($members[$b]);
    };
    foreach ($bases as $ids) {
        $primary=$ids[0];
        foreach ($ids as $id) if ($sources[$id]['kind']==='primary') $primary=$id;
        foreach ($ids as $id) $join($primary,$id);
    }
    foreach ($events as $e) {
        if ($e['undone'] || !in_array($e['action'],['link','prefer'],true)) continue;
        $ids=array_merge($e['left'],$e['right']);
        foreach ($ids as $i=>$id) foreach (array_slice($ids,$i+1) as $other) $join($id,$other);
        if ($e['preferred']) $prefs[]=$e['preferred'];
    }

    // Sorted starts allow early exit, including for trimmed, overlapping recordings.
    $candidates = $strongChoices = [];
    $ids = array_keys($sources);
    foreach ($ids as $i=>$a) {
        $x=$sources[$a];
        for ($j=$i+1; $j<count($ids); $j++) {
            $b=$ids[$j]; $y=$sources[$b];
            $delta=$y['from']-$x['from'];
            if ($delta>90 && $y['from']>=($x['to'] ?? 0)) break;
            if ($roots[$a]===$roots[$b] || !$canJoin($a,$b)) continue;
            $overlap=min($x['to'] ?? 0,$y['to'] ?? 0)-$y['from'];
            $shorter=min(($x['to'] ?? 0)-$x['from'],($y['to'] ?? 0)-$y['from']);
            $longer=max(($x['to'] ?? 0)-$x['from'],($y['to'] ?? 0)-$y['from']);
            // A very long or corrupt interval containing a short activity is weak evidence.
            if ($delta>90 && ($shorter<=0 || $overlap < .8*$shorter || $overlap < .5*$longer)) continue;
            $sameBoundaries = $delta === 0 && $x['to'] !== null && $x['to'] === $y['to'];
            $sameDistance = $x['recordedKm'] > 0 && $y['recordedKm'] > 0
                && round($x['recordedKm'], 2) === round($y['recordedKm'], 2);
            // Stationary sessions need corroboration beyond two zero distances.
            $sameStationaryWorkout = $x['recordedKm'] === 0.0 && $y['recordedKm'] === 0.0
                && in_array($x['sport'], ['Workout', 'WeightTraining'], true) && $x['sport'] === $y['sport']
                && trim($x['title'] ?? '') !== '' && strcasecmp(trim($x['title']), trim($y['title'] ?? '')) === 0
                && $x['timer'] > 0 && $y['timer'] > 0 && abs($x['timer'] - $y['timer']) <= 1;
            $providers = [$x['provider'], $y['provider']];
            $auto = $sameBoundaries && ($sameDistance || $sameStationaryWorkout) && !$x['edited'] && !$y['edited']
                && in_array('garmin', $providers, true) && in_array('strava', $providers, true);
            $similarDistance = min($x['km'], $y['km']) > 0
                && abs($x['km'] - $y['km']) <= max(.05, .15 * max($x['km'], $y['km']));
            $sameSport = $x['sport'] && $x['sport'] === $y['sport'];
            $similarLength = $x['elapsed'] > 0 && $y['elapsed'] > 0
                && min($x['elapsed'], $y['elapsed']) >= .5 * max($x['elapsed'], $y['elapsed']);
            // Timing alone can suggest an overlap, but a concurrent workout should not be a top priority.
            $strong = $sameBoundaries && ($sameDistance || $sameStationaryWorkout) && !$x['edited'] && !$y['edited'];
            $sameStartWithDistance = $delta === 0 && $x['km'] > 0 && $y['km'] > 0;
            $supported = $strong || $similarDistance || ($sameSport && ($similarLength || $sameStartWithDistance));
            if (!$supported && $delta > 90) continue;
            $confidence = $strong ? 2 : (int)$supported;
            $candidates[] = [$a, $b, $auto, $delta === 0 ? 'Same recording start'
                : ($delta <= 90 ? 'Starts within 90 seconds' : 'Recording periods overlap'), $confidence];
        }
    }
    $remembered = workout_review_query($ctx, 'SELECT garmin,strava FROM mattrics.workout_auto_pairs WHERE user_id=:user ORDER BY garmin,strava');
    $known = [];
    foreach ($candidates as [$a,$b]) $known[workout_review_key($a,$b)] = true;
    $previous = [];
    foreach ($remembered as $pair) {
        $a = $pair['garmin']; $b = $pair['strava'];
        if ($roots[$a] === $roots[$b] || !$canJoin($a,$b)) continue;
        $key = workout_review_key($a,$b);
        $previous[$key] = true;
        if (!isset($known[$key])) {
            $candidates[] = [$a, $b, true, 'Previously matched recording', 2];
        }
    }
    foreach ($candidates as [$a, $b, $auto, $why, $confidence]) {
        if ($confidence === 2 || isset($previous[workout_review_key($a, $b)])) {
            $strongChoices[$a] = ($strongChoices[$a] ?? 0) + 1;
            $strongChoices[$b] = ($strongChoices[$b] ?? 0) + 1;
        }
    }
    $automatic = $queue = [];
    foreach ($candidates as [$a, $b, $auto, $why, $confidence]) {
        $key = workout_review_key($a, $b);
        if (($auto || isset($previous[$key])) && $strongChoices[$a] === 1 && $strongChoices[$b] === 1
            && count($members[$roots[$a]]) === 1 && count($members[$roots[$b]]) === 1 && !isset($later[$key])) {
            [$garmin, $strava] = $sources[$a]['provider'] === 'garmin' ? [$a, $b] : [$b, $a];
            $join($garmin, $strava);
            if (!isset($previous[$key])) $automatic[] = [$garmin, $strava];
        }
    }
    foreach ($candidates as [$a, $b, $auto, $why, $confidence]) {
        if ($roots[$a] !== $roots[$b] && $canJoin($a, $b)) {
            $queue[] = ['left'=>$a, 'right'=>$b, 'reason'=>$why,
                'later'=>isset($later[workout_review_key($a, $b)]), 'confidence'=>$confidence];
        }
    }
    foreach ($later as $key => [$a, $b]) {
        if (!isset($known[$key]) && !isset($previous[$key]) && isset($roots[$a], $roots[$b])
            && $roots[$a] !== $roots[$b] && $canJoin($a, $b)) {
            $queue[] = ['left'=>$a, 'right'=>$b, 'reason'=>null, 'later'=>true];
        }
    }
    $groups=[];
    foreach ($members as $root=>$ids) {
        $winner=workout_review_default($ids,$sources);
        foreach ($prefs as $pref) if (in_array($pref,$ids,true)) $winner=$pref;
        $groups[$root]=['ids'=>$ids,'preferred'=>$winner,'explicit'=>in_array($winner,$prefs,true),'activity'=>$sources[$root]['kind']==='primary'?$sources[$root]['base']:$root];
    }
    $version=hash('sha256',serialize([$sources,$events,$legacy,$remembered]));
    return compact('sources','groups','roots','queue','blocked','version','last','lastPair','automatic');
}

function workout_review_remember_automatic(PDO $pdo, string $userId): void
{
    $state = workout_review_state(['pdo'=>$pdo,'userId'=>$userId]);
    $insert = $pdo->prepare('INSERT INTO mattrics.workout_auto_pairs (user_id,garmin,strava) VALUES (?,?,?) ON CONFLICT DO NOTHING');
    foreach ($state['automatic'] as [$garmin,$strava]) $insert->execute([$userId,$garmin,$strava]);
}

function workout_review_change(array $ctx, array $request): array
{
    $pdo=$ctx['pdo'];
    $pdo->beginTransaction();
    try {
        // Same lock order as archive review and the transactional import user upsert.
        $pdo->query('SELECT pg_advisory_xact_lock(481871, 2)');
        workout_review_query($ctx,'SELECT id FROM mattrics.mattrics_users WHERE id=:user FOR UPDATE');
        $state=workout_review_state($ctx);
        if (($request['version'] ?? null) !== $state['version']) {
            throw new DomainException('Recordings changed. Reload the comparison before saving.',409);
        }
        $action=$request['action'] ?? '';
        if ($action==='undo' && $state['last']!==null) {
            $stmt=$pdo->prepare('UPDATE mattrics.workout_reviews SET undone=true WHERE user_id=? AND revision=?');
            $stmt->execute([$ctx['userId'],$state['last']]);
        } else {
            $a=$request['left'] ?? '';
            $b=$request['right'] ?? '';
            $pref=$request['preferred'] ?? null;
            if (!is_string($a) || !is_string($b) || !isset($state['roots'][$a],$state['roots'][$b])
                || $a===$b || !in_array($action,['link','separate','later','prefer'],true)) {
                throw new DomainException('Select two recordings and a valid action.',422);
            }
            $left=$state['groups'][$state['roots'][$a]]['ids'];
            $right=$state['groups'][$state['roots'][$b]]['ids'];
            if ($left===$right) {
                if ($action==='separate') {
                    $left=array_values(array_diff($left,[$b]));
                    $right=[$b];
                } elseif ($action==='prefer') {
                    $right=[];
                } else throw new DomainException('These recordings are already linked.',422);
            } elseif ($action==='prefer') throw new DomainException('Choose a source for a linked workout.',422);
            // Adding a recording to an established workout preserves its dashboard ID,
            // regardless of the order in which the reviewer selected the recordings.
            if ($action === 'link' && count($left) === 1 && count($right) > 1) {
                [$left, $right] = [$right, $left];
            }
            if (in_array($action,['link','prefer'],true)) {
                if (!in_array($pref,array_merge($left,$right),true)) throw new DomainException('Select an available source.',422);
            } else $pref=null;
            if ($action === 'later') {
                // Store the exact comparison so a manually selected pair can be reopened.
                $left = [$a];
                $right = [$b];
            }
            $stmt=$pdo->prepare('INSERT INTO mattrics.workout_reviews
                (user_id,action,left_sources,right_sources,preferred) VALUES (?,?,?::uuid[],?::uuid[],?)');
            $stmt->execute([$ctx['userId'],$action,'{'.implode(',',$left).'}','{'.implode(',',$right).'}',$pref]);
        }
        $next=workout_review_state($ctx);
        $pdo->commit();
        return $next;
    } catch (Throwable $failure) {
        $pdo->rollBack();
        throw $failure;
    }
}

function workout_review_public(array $state): array
{
    $fields=array_flip(['id','provider','title','sport','start','finish','selectedStart','selectedFinish','moving','timer','elapsed','label','seconds','km','url','edited']);
    return [
        'sources'=>array_map(static fn($s)=>array_intersect_key($s,$fields),$state['sources']),
        'groups'=>array_values($state['groups']), 'queue'=>$state['queue'], 'version'=>$state['version'],
        'canUndo'=>$state['last']!==null, 'undoPair'=>$state['lastPair'],
    ];
}

function workout_review_project(array $data,array $state): array
{
    $original=array_column($data['rows'],null,'Activity ID raw');
    $children=array_column($data['activityChildren'] ?? [],null,'activityIdRaw');
    $remaining=$original;
    $rows=$mappedChildren=[];
    foreach ($state['groups'] as $group) {
        $s=$state['sources'][$group['preferred']];
        $row=$original[$s['base']];
        $row['Activity ID']=$row['Activity ID raw']=$group['activity'];
        $row['Name']=$s['title'] ?: $row['Name'];
        $row['Type']=$s['sport'] ?: $row['Type'];
        $row['Date']=$s['selectedStart'];
        $row['Distance (km)']=$s['km'];
        $row['Duration (min)']=$s['seconds']===null?null:$s['seconds']/60;
        $row['Duration basis']=$s['label'];
        foreach (['Moving'=>'moving','Timer'=>'timer','Elapsed'=>'elapsed'] as $name=>$key) $row[$name.' seconds']=$s[$key];
        if ($s['provider']!=='hevy') $row['Description']=$s['description'];
        $row['Avg HR']=$s['hr'];
        $row['Max HR']=$s['max_hr'];
        $row['Elevation Gain (m)']=$s['ascent'];
        $row['Device Name']=$s['device'];
        foreach (['Avg Pace (min/km)','Avg Speed (km/h)'] as $field) $row[$field]=null;
        $row['Avg Cadence'] = $s['edited'] ? null : $s['cadence'];
        if ($s['km']>0 && $s['seconds']>0) {
            $row['Avg Pace (min/km)']=$s['seconds']/60/$s['km'];
            $row['Avg Speed (km/h)']=$s['km']*3600/$s['seconds'];
        }
        $exercises=[];
        foreach ($group['ids'] as $id) {
            $other=$state['sources'][$id];
            unset($remaining[$other['base']]);
            if ($other['provider']==='hevy' && isset($children[$other['base']])) {
                if (!$exercises || $id === $group['preferred']) {
                    $exercises = $children[$other['base']]['exercises'];
                }
            }
            if (!$s['edited'] && $s['to']!==null && $other['provider']==='garmin'
                && $s['from']===$other['from'] && $s['to']===$other['to']) {
                $row['Avg HR']??=$other['hr'];
                $row['Max HR']??=$other['max_hr'];
            }
        }
        if ($exercises) $mappedChildren[]=['activityIdRaw'=>$group['activity'],'activityId'=>$group['activity'],'exercises'=>$exercises];
        $rows[]=$row;
    }
    foreach ($remaining as $id=>$row) {
        $rows[]=$row;
        if (isset($children[$id])) $mappedChildren[]=$children[$id];
    }
    usort($rows,static fn($a,$b)=>strcmp($b['Date'],$a['Date']) ?: strcmp($a['Activity ID raw'],$b['Activity ID raw']));
    $data['rows']=$rows;
    $data['count']=count($rows);
    if (isset($data['activityChildren'])) $data['activityChildren']=$mappedChildren;
    $data['meta']['workoutReview']=true;
    return $data;
}
