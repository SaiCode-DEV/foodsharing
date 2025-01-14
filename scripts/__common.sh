#!/usr/bin/env bash

# set -o errexit tells the shell to exit as soon as a command exits with non-zero status, i.e. fails
set -o errexit
set -o nounset
set -o pipefail
# :- is an shell operator. If FS_ENV is set and not the empty string, use FS_ENV, otherwise use dev
export FS_ENV=${FS_ENV:-dev}

# user identification number of the current user
CURRENT_USER=$(id -u):$(id -g)
export CURRENT_USER

MYSQL_USERNAME=${MYSQL_USERNAME:-root}
MYSQL_PASSWORD=${MYSQL_PASSWORD:-root}

# docker-compose arguments:
# -T : do not allocate a TTY: not necessary since we just execute a command
# but need not interactivity
# see: https://docs.docker.com/compose/reference/exec/

# sh -c "..." : run the command "..." in a shell

# BASH_SOURCE is an array with the filenames of the files that were called to get here
# so BASH_SOURCE[0] is the filename (with path) of this file
# different to $0 when this file is sourced with "." or source as in many of the scripts
dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)

function log-header() {
  # print a log header, take one argument as the printed title
  local text=$1;
  echo
  echo "============================================"
  echo "  $text"
  echo "============================================"
}

function sql-query() {
  local database=$1 query=$2;
  "$dir"/docker-compose exec -T db sh -c "mariadb --password=$MYSQL_PASSWORD $database --execute=\"$query\""
}

function sql-file() {
  local database=$1 filename=$2;
  echo "Executing sql file $FS_ENV/$database $filename"
  "$dir"/docker-compose exec -T db sh -c "mariadb --password=$MYSQL_PASSWORD $database < /app/$filename"
}

function sql-dump() {
  "$dir"/docker-compose exec -T db mariadb-dump --password="$MYSQL_PASSWORD" foodsharing "$@"
}

function sql_dump_to_file() {
  local filename=$1
  "$dir"/docker-compose exec -T db sh -c "mariadb-dump --password=$MYSQL_PASSWORD foodsharing > /tmp/dump.sql"
  "$dir"/docker-compose cp db:/tmp/dump.sql $filename
}

function exec-in-container() {
  local container=$1; shift;
  local command=$*;
  "$dir"/docker-compose exec -T --user "$CURRENT_USER" "$container" sh -c "HOME=./ $command"
}

function exec-in-container-with-image-user() {
  local container=$1; shift;
  local command=$*;
  "$dir"/docker-compose exec -T "$container" sh -c "HOME=./ $command"
}

function run-in-container() {
  local container=$1; shift;
  local command=$*;
  "$dir"/docker-compose run --rm --no-deps --user "$CURRENT_USER" "$container" sh -c "HOME=./ $command"
}

function run-in-container-with-service-ports() {
  local container=$1; shift;
  local command=$*;
  "$dir"/docker-compose run --rm --no-deps --user "$CURRENT_USER" --service-ports "$container" sh -c "HOME=./ $command"
}

function exec-in-container-asroot() {
  local container=$1; shift;
  local command=$*;
  "$dir"/docker-compose exec --user root -T "$container" sh -c "$command"
}

function run-in-container-asroot() {
  local container=$1; shift;
  local command=$*;
  # run : create a new container to execute the command
  # --user root : set the user who executes the command
  # --rm : remove the container after executing the command
  # sh -c "..." : what is executed in the container: a shell that
  # interprets "..."
  "$dir"/docker-compose run --rm --no-deps --user root "$container" sh -c "$command"
}

function dropdb() {
  local database=$1;
  echo "Dropping database $FS_ENV/$database"
  sql-query mysql "drop database if exists $database"
}

function createdb() {
  local database=$1;
  echo "Creating database $FS_ENV/$database"
  sql-query mysql "\
    create database if not exists $database; \
    alter database $database character set = utf8mb4 collate = utf8mb4_unicode_ci; \
  "
}

function recreatedb() {
  local database=$1;
  dropdb "$database"
  createdb "$database"
}

function migratedb() {
  echo "Migrating database for $FS_ENV"
  local container=${1:-app}
  exec-in-container "$container" vendor/bin/phinx migrate
  exec-in-container "$container" bin/console maintenance:recreateGroupStructure
}

function wait-for-mariadb() {
  exec-in-container-asroot db "while ! mariadb --password=$MYSQL_PASSWORD --silent --execute='select 1' >/dev/null 2>&1; do sleep 1; done"
}

function wait-for-assets() {
  start_time=$(date +%s)
  while ! [ "$(ls -A assets)" ]; do
    sleep 1
    current_time=$(date +%s)
    elapsed_time=$((current_time - start_time))
    echo -ne "\rElapsed time: ${elapsed_time}s"
  done
  echo
}

function wait-for-api-dump() {
  while [ $(wc -l < docs/data/api_dump.json) -lt 1000 ];
  do
    sleep 1;
    echo -ne ".";
  done
  echo
}

function gitlab_start_section() {
  local section_id=$1; shift
  local message=$*
  echo -e "\e[0Ksection_start:$(date +%s):section_$section_id\r\e[0K$message"
}

function gitlab_end_section() {
  local section_id=$1
  echo -e "\e[0Ksection_end:$(date +%s):section_$section_id\r\e[0K"
}

function run_codeception() {
  exec-in-container selenium "find /home/seluser/Downloads -type f -print0 | xargs -r -n 1 -0 rm"
  exec-in-container-asroot app rm --recursive --force cache/.views-cache cache/di-cache.php
  exec-in-container app vendor/bin/codecept run "$@"
}
