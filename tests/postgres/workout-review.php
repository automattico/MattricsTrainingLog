<?php
declare(strict_types=1);

function test_workout_review(PDO $pdo, array $config, array $garmin, array $hevy): void
{
    $user = 'review-feature-test';
    $config['foundation_user_key'] = $user;
    $import = static fn($provider,$records) => local_workout_import($pdo,$user,$provider,'review-fixture',$records);
    $strava = $garmin;
    $strava['id'] = '12345';
    $strava['fit_sha256'] = str_repeat('a',64);
    $strava['title'] = 'Edited paddle';
    $strava['sport'] = 'Canoe';
    $strava['archive_evidence'] = ['distance_columns'=>['5.04','5040'],'moving_time'=>'1200','elapsed_columns'=>['1860']];
    $import('garmin',[$garmin]);
    $import('strava',[$strava]);
    with_test_router($config, static function ($host) use ($pdo,$import,$hevy,$strava,$garmin): void {
        $review = static fn() => api_response($host,'/api/workout-review');
        $rows = static fn() => api_response($host,'/api/data?includeChildren=1');
        $save = static fn($state,$action,$left,$right,$preferred=null,$status=200) => api_response($host,'/api/workout-review',
            ['version'=>$state['version'],'action'=>$action,'left'=>$left,'right'=>$right,'preferred'=>$preferred],$status);
        $state=$review();
        $ids=array_column($state['sources'],'id','provider');
        $g=$ids['garmin']; $s=$ids['strava'];
        $data=$rows();
        check($data['count']===1 && $data['rows'][0]['Name']==='Edited paddle' && $data['rows'][0]['Duration (min)']==20,
            'unique equal recording boundaries and distance link across sports and select Strava moving duration');
        $originalId=$data['rows'][0]['Activity ID raw'];
        $state=$save($state,'separate',$g,$s);
        check($rows()['count']===2 && !$state['queue'], 'keep separate splits an automatic link and suppresses suggestions');
        $state=$save($state,'undo',$g,$s);
        check($rows()['count']===1, 'undo restores automatic link');
        $stale=$state;
        $state=$save($state,'prefer',$g,$s,$s);
        $save($stale,'prefer',$g,$s,$g,409);
        $linkBeforeTrim = linked_record($pdo, 'strava', 'review-fixture', $strava['id']);
        $strava['title']='Trimmed in Strava';
        $strava['archive_evidence']=['distance_columns'=>['2.00','2000'],'moving_time'=>'600','elapsed_columns'=>['800']];
        $import('strava',[$strava]);
        $import('garmin',[$garmin]);
        $data=$rows();
        $linkAfterTrim = linked_record($pdo, 'strava', 'review-fixture', $strava['id']);
        check($data['count']===1 && $data['rows'][0]['Name']==='Trimmed in Strava'
            && $data['rows'][0]['Distance (km)']==2 && $data['rows'][0]['Duration (min)']==10
            && $data['rows'][0]['Avg HR']===null && $data['rows'][0]['Activity ID raw']===$originalId
            && $linkBeforeTrim['id'] === $linkAfterTrim['id'] && $linkBeforeTrim['activity_id'] === $linkAfterTrim['activity_id'],
            'pinned choice survives re-import and uses edited summary without whole-recording heart rate');
        $hevy['start_time']=$garmin['session']['start_time'];
        $hevy['end_time']=$garmin['session']['timestamp'];
        $import('hevy',[$hevy]);
        $state=$review();
        $h=array_column($state['sources'],'id','provider')['hevy'];
        $state=$save($state,'link',$h,$g,$h);
        $data=$rows();
        $children=$data['activityChildren'];
        check($data['count']===1 && $data['rows'][0]['Type']==='WeightTraining' && count($children)===1
            && $children[0]['activityIdRaw']===$originalId && $children[0]['exercises'][0]['hevyTemplateKey']
            && $children[0]['exercises'][0]['sets'] && $data['rows'][0]['Avg HR']==148,
            'three-source workout retains Hevy sets and template keys with compatible Garmin heart rate');
        $state=$save($state,'prefer',$g,$s,$g);
        check($rows()['activityChildren']===$children, 'changing preferred source preserves structured children');
        $state=$save($state,'undo',$g,$s);
        $state=$save($state,'undo',$g,$s);
        check($rows()['count']===2 && $rows()['activityChildren'][0]['exercises']===$children[0]['exercises'],
            'undo three-source link restores independent workout and its sets');
        $state=$save($state,'later',$g,$h);
        check(in_array(true,array_column($state['queue'],'later'),true),'deferred pair remains available for review');
        $state=$save($state,'separate',$g,$h);
        $import('hevy',[$hevy]);
        check(!$review()['queue'] && $rows()['count']===2,'separate overlapping warm-up remains independent across replay');
    });
    $config['foundation_user_key'] = 'ambiguous-repeat-test';
    $second = $garmin;
    $second['id'] .= '-second';
    $second['fit_sha256'] = str_repeat('b', 64);
    $tomorrow = $garmin;
    $tomorrow['id'] .= '-next-day';
    $tomorrow['fit_sha256'] = str_repeat('c', 64);
    foreach (['start_time','timestamp'] as $key) {
        $tomorrow['session'][$key] = (new DateTimeImmutable($garmin['session'][$key]))->modify('+1 day')->format('c');
    }
    local_workout_import($pdo, $config['foundation_user_key'], 'garmin', 'repeat', [$garmin,$second,$tomorrow]);
    local_workout_import($pdo, $config['foundation_user_key'], 'strava', 'repeat', [$strava]);
    with_test_router($config, static function ($host): void {
        $state = api_response($host, '/api/workout-review');
        check(count($state['groups']) === 4 && count($state['queue']) === 3,
            'multiple same-day candidates stay separate and the next-day repeat has no suggestion');
        $recordings = array_values($state['sources']);
        $left = $recordings[0]['id'];
        $right = $recordings[3]['id'];
        $saved = api_response($host, '/api/workout-review', [
            'action'=>'later', 'left'=>$left, 'right'=>$right, 'version'=>$state['version'],
        ]);
        $reloaded = api_response($host, '/api/workout-review');
        check($saved['undoPair'] === [$left, $right] && count($reloaded['queue']) === 4
            && in_array(['left'=>$left, 'right'=>$right, 'reason'=>null, 'later'=>true], $reloaded['queue'], true),
            'manually selected recordings stay in saved comparisons across reload');
        $undone = api_response($host, '/api/workout-review', ['action'=>'undo', 'version'=>$reloaded['version']]);
        check($undone['undoPair'] === null && $undone['queue'] === $state['queue'],
            'undo restores the queue before a manual deferral');
    });
    $config['foundation_user_key'] = 'stationary-workout-test';
    $stationary = $garmin;
    $stationary['sport'] = 'Workout';
    $stationary['session']['total_distance'] = 0;
    $stationary['archive_evidence'] = ['garmin_summary'=>['movingDuration'=>0]];
    $stravaStationary = $stationary;
    $stravaStationary['id'] = '23456';
    $stravaStationary['fit_sha256'] = str_repeat('d', 64);
    $stravaStationary['archive_evidence'] = ['moving_time'=>'1860', 'distance_columns'=>['0','0']];
    $importStationary = static fn($provider, $record) => local_workout_import(
        $pdo, $config['foundation_user_key'], $provider, 'stationary', [$record]
    );
    $importStationary('garmin', $stationary);
    $importStationary('strava', $stravaStationary);
    $overlap = $stravaStationary;
    $overlap['id'] = '34567';
    $overlap['title'] = 'Separate walk';
    $overlap['sport'] = 'Walk';
    $overlap['fit_sha256'] = str_repeat('f', 64);
    $overlap['session']['total_distance'] = 2300;
    unset($overlap['archive_evidence']);
    $importStationary('strava', $overlap);
    with_test_router($config, static function ($host): void {
        $state = api_response($host, '/api/workout-review');
        $group = array_values(array_filter($state['groups'], static fn($g) => count($g['ids']) === 2))[0];
        check(count($state['groups']) === 2 && $state['sources'][$group['preferred']]['provider'] === 'strava'
            && max(array_column($state['queue'], 'confidence')) === 0,
            'weak competitor does not prevent a stationary Garmin and Strava match');
        $garmin = $group['ids'][0];
        check($state['sources'][$garmin]['moving'] === 0 && $state['sources'][$garmin]['seconds'] === 1800
            && $state['sources'][$garmin]['label'] === 'Timer'
            && $state['sources'][$group['preferred']]['seconds'] === 1800,
            'stationary workouts count timer duration while preserving zero moving time');
        $split = api_response($host, '/api/workout-review', [
            'action'=>'separate', 'left'=>$garmin, 'right'=>$group['ids'][1], 'version'=>$state['version'],
        ]);
        check(count($split['groups']) === 3 && max(array_column($split['queue'], 'confidence')) === 0,
            'keep separate overrides the stationary match rule');
    });

}
