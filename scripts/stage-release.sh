#!/bin/sh
set -eu

root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$root"
die() { printf 'stage-release: %s\n' "$*" >&2; exit 1; }
[ "$#" -eq 2 ] || die 'usage: stage-release.sh REVISION NEW_DIRECTORY'
revision=$(git rev-parse --verify --end-of-options "$1^{commit}") || die 'expected a commit'
destination=$2

required='public/index.html
public/robots.txt
public/site.webmanifest
public/assets/css/main.css
public/assets/js/app.js
api/session.php
api/data.php
api/settings.php
api/connectors.php
api/exercises.php
api/ai.php
lib/bootstrap.php
private/config.example.php
private/data/activity-type-configs.json
private/data/exercise-configs.json
private/data/exercise-dataset.json
private/data/exercise-unknowns.json'
review='api/workout-review.php
public/assets/js/workout-review.js
public/icons/review-garmin.jpg
public/icons/review-strava.jpg
lib/workout-review.php
lib/direct-refresh.php
lib/direct-providers.php
lib/linked-workout-import.php
lib/fit_activity.py'

listed=$(git -c core.quotePath=false ls-tree -r "$revision" -- public api lib private)
allowlisted=$(printf '%s\n' "$listed" | LC_ALL=C awk -F '\t' '
    $2 ~ /^public\/(index\.html|robots\.txt|site\.webmanifest|assets\/.+|icons\/.+)$/ ||
    $2 ~ /^api\/(session|data|settings|connectors|exercises|ai|workout-review)\.php$/ ||
    $2 ~ /^lib\/([A-Za-z0-9_-]+\.php|fit_activity\.py)$/ ||
    $2 ~ /^private\/(config\.example\.php|data\/(activity-type-configs|exercise-configs|exercise-dataset|exercise-unknowns)\.json)$/ {
        if ($1 !~ /^100(644|755) blob / || $2 !~ /^[A-Za-z0-9_.\/-]+$/ || $2 ~ /\/\./) exit 1
        print $2
    }') || die 'invalid path or file type in the upload set'

if printf '%s\n' "$allowlisted" | grep -qE '^(api/workout-review\.php|public/assets/js/workout-review\.js|lib/(workout-review\.php|direct-refresh\.php|fit_activity\.py))$'; then
    required="$required
$review"
fi
printf '%s\n' "$required" | while IFS= read -r path; do
    printf '%s\n' "$allowlisted" | grep -qFx "$path" || die "commit missing $path"
done

umask 077
mkdir -- "$destination" || die 'destination must be a new directory'
printf '%s\n' "$allowlisted" | while IFS= read -r path; do
    mkdir -p -- "$destination/${path%/*}"
    git cat-file blob "$revision:$path" > "$destination/$path"
    [ "$(git hash-object --no-filters "$destination/$path")" = "$(git rev-parse "$revision:$path")" ] ||
        die "staged content differs from $path"
done

actual=$(cd "$destination" && find public api lib private -type f | LC_ALL=C sort)
expected=$(printf '%s\n' "$allowlisted" | LC_ALL=C sort)
[ "$actual" = "$expected" ] || die 'staged inventory differs from the reviewed commit'
printf 'Staged and verified %s in %s\n' "$revision" "$destination"
