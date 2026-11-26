<?php
declare(strict_types=1);

function postgres_dashboard_analysis(string $script, array $input): mixed
{
    $process = proc_open(
        ['node', __DIR__ . '/' . $script],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => STDERR],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start dashboard analysis.');
    }
    fwrite($pipes[0], json_encode($input, JSON_THROW_ON_ERROR));
    fclose($pipes[0]);
    $result = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Dashboard analysis failed.');
    }
    return json_decode($result, true, 512, JSON_THROW_ON_ERROR);
}

function analyze_hevy_history(array $data, string $workoutId, array $before, ?array $after = null): array
{
    return postgres_dashboard_analysis('hevy-analysis.js', compact('data', 'workoutId', 'before', 'after'));
}

function hevy_review_children(array $data, string $workoutId): array
{
    return array_column($data['activityChildren'], 'exercises', 'activityIdRaw')[$workoutId];
}

function run_hevy_identity_review(PDO $pdo, array $config, array $workout): void
{
    $account = 'synthetic-hevy-review';
    $workout['id'] = 'synthetic-same-title-workout';
    $sharedTitle = 'Synthetic Shared Press';
    $workout['exercises'] = [
        [
            'title' => $sharedTitle,
            'exercise_template_id' => 'SYNTH-PRESS-A',
            'sets' => [
                ['type' => 'warmup', 'weight_kg' => 20, 'reps' => 8],
                ['type' => 'normal', 'weight_kg' => 60, 'reps' => 8, 'rpe' => 8.5],
                ['type' => 'normal', 'weight_kg' => 65, 'reps' => 8],
                ['type' => 'normal', 'weight_kg' => 70, 'reps' => 8],
            ],
        ],
        [
            'title' => $sharedTitle,
            'exercise_template_id' => 'SYNTH-PRESS-B',
            'sets' => [
                ['type' => 'normal', 'weight_kg' => 115, 'reps' => 7],
                ['type' => 'normal', 'weight_kg' => 120, 'reps' => 8],
                ['type' => 'normal', 'weight_kg' => 125, 'reps' => 6],
            ],
        ],
    ];
    $import = static fn(array $record, ?string $source = null): array =>
        local_workout_import($pdo, $config['foundation_user_key'], 'hevy', $source ?? $account, [$record]);
    $import($workout);
    $workoutId = linked_record($pdo, 'hevy', $account, $workout['id'])['activity_id'];

    with_test_router($config, static function (string $address) use ($pdo, $workout, $workoutId, $account, $import, $sharedTitle): void {
        $dataPath = '/api/data?includeChildren=1';
        $catalogPath = '/api/exercises';
        $createTarget = static fn(string $title, string $muscle): array => api_response($address, $catalogPath, [
            'canonicalName' => $title,
            'aliases' => [],
            'matchTerms' => [],
            'muscleWeights' => [$muscle => 1],
            'bodyweightEligible' => false,
            'setTypeHandling' => 'weight_reps',
        ], 201)['exercise'];
        $press = $createTarget($sharedTitle, 'chest');
        $legPress = $createTarget('Synthetic Leg Press', 'quadriceps');
        $catalog = api_response($address, $catalogPath);
        $initial = api_response($address, $dataPath);
        $initialChildren = hevy_review_children($initial, $workoutId);
        [$first, $second] = $initialChildren;
        check(array_column($initialChildren, 'sourceExerciseName') === [$sharedTitle, $sharedTitle]
            && array_column($initialChildren, 'providerTemplateId') === ['SYNTH-PRESS-A', 'SYNTH-PRESS-B']
            && $first['hevyTemplateKey'] !== $second['hevyTemplateKey']
            && array_column($first['sets'], 'weightKg') === [20, 60, 65, 70]
            && array_column($second['sets'], 'weightKg') === [115, 120, 125]
            && array_column($second['sets'], 'reps') === [7, 8, 6]
            && $first['sets'][0]['setType'] === 'warmup' && $first['sets'][1]['rpe'] === 8.5,
            'SQL-backed data API preserves both same-title templates and their distinct structured sets');

        $unknowns = analyze_hevy_history($initial, $workoutId, $catalog)['before']['unknowns'];
        $queue = static fn(array $records): array => api_response($address, $catalogPath, ['unknowns' => $records])['unknowns'];
        [$unknownA, $unknownB] = $queue($unknowns);
        check(count($unknowns) === 2 && $unknownA['id'] !== $unknownB['id']
            && $unknownA['hevyTemplateKey'] === $first['hevyTemplateKey']
            && $unknownB['hevyTemplateKey'] === $second['hevyTemplateKey'],
            'the supported review endpoint queues same-title templates separately, including a catalog title match');
        $map = static fn(array $unknown, array $target): array => api_response(
            $address,
            "$catalogPath/unknowns/" . rawurlencode($unknown['id']) . '/merge-alias',
            ['targetExerciseId' => $target['id']]
        );
        $map($unknownA, $press);
        $import($workout);
        check(hevy_review_children(api_response($address, $dataPath), $workoutId) === $initialChildren,
            'exact replay preserves each template and all set rows');

        $renamed = $workout;
        $renamed['exercises'][0]['title'] = 'Synthetische Presse';
        $import($renamed);
        $renamedData = api_response($address, $dataPath);
        $renamedChildren = hevy_review_children($renamedData, $workoutId);
        $catalogBeforeReview = api_response($address, $catalogPath);
        $before = analyze_hevy_history($renamedData, $workoutId, $catalogBeforeReview)['before'];
        check(linked_record($pdo, 'hevy', $account, $workout['id'])['activity_id'] === $workoutId
            && $renamedChildren[0]['sourceExerciseName'] === 'Synthetische Presse'
            && $renamedChildren[0]['hevyTemplateKey'] === $first['hevyTemplateKey']
            && $renamedChildren[0]['sets'] === $first['sets']
            && $renamedChildren[1] === $second
            && $before['targets'] === [$press['id'], null]
            && array_column($before['unknowns'], 'id') === [$unknownB['id']],
            'a title change retains the first template mapping and both exercises keep their sets');

        $catalogAfterReview = $map($unknownB, $legPress);
        $analysis = analyze_hevy_history($renamedData, $workoutId, $catalogBeforeReview, $catalogAfterReview);
        check($analysis['before']['stimulus']['quadriceps'] === 0 && $analysis['after']['stimulus']['quadriceps'] > 0
            && $analysis['after']['stimulus']['chest'] === $analysis['before']['stimulus']['chest']
            && $analysis['after']['references'][$press['id']]['loadKg'] === 65
            && $analysis['after']['references'][$legPress['id']]['source'] === 'exercise_history'
            && $analysis['after']['references'][$legPress['id']]['loadKg'] === 120
            && $analysis['after']['targets'] === [$press['id'], $legPress['id']]
            && $analysis['after']['unknowns'] === [] && $catalogAfterReview['unknowns'] === []
            && $catalogAfterReview['exercise']['aliases'] === []
            && $queue($unknowns) === [],
            'review updates historical fatigue and reference loads in place, and stale scans cannot reopen resolved templates');

        $otherAccount = 'synthetic-other-review';
        $import($workout, $otherAccount);
        $otherId = linked_record($pdo, 'hevy', $otherAccount, $workout['id'])['activity_id'];
        $otherData = api_response($address, $dataPath);
        $other = analyze_hevy_history($otherData, $otherId, api_response($address, $catalogPath))['before'];
        check($other['targets'] === [null, null] && count($other['unknowns']) === 2,
            'identical provider template IDs in another account keep independent mappings');
        $otherQueue = $queue($other['unknowns']);
        $created = api_response($address, $catalogPath, [
            'unknownId' => $otherQueue[0]['id'],
            'canonicalName' => 'Synthetic Shoulder Press',
            'aliases' => [],
            'matchTerms' => [],
            'muscleWeights' => ['deltoids' => 1],
            'bodyweightEligible' => false,
            'setTypeHandling' => 'weight_reps',
        ], 201);
        check(analyze_hevy_history($otherData, $otherId, $created)['before']['targets'] === [$created['exercise']['id'], null]
            && array_column($created['unknowns'], 'id') === [$otherQueue[1]['id']],
            'creating a config from review assigns only the selected account template');
    });
}
