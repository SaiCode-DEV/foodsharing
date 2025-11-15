#!/usr/bin/env bash

set -o errexit
dir=$(dirname "$0")
source "$dir"/__common.sh

## Environment port mapping for the url creation
if [ "$FS_ENV" == "dev" ]
then
  page_port=18080 #nginx
  phpmyadmin_port=18081
  devdocs_port=13000
  zammad_port=18087
  listmonk_port=18088
elif [ "$FS_ENV" == "test" ]
then
  page_port=28080 #nginx
  phpmyadmin_port=28081
  devdocs_port=23000
else
  page_port=8080 #nginx
  phpmyadmin_port=8081
  devdocs_port=3000
fi

# Cyrptic gitpod or localhost urls for the print
if [ "$USER" == "gitpod" ]
then
    page_url=$(gp url $page_port)
    api_url="$page_url/api/doc/"
    devdocs_url=$(gp url $devdocs_port)
    phpmyadmin_url=$(gp url $phpmyadmin_port)
    zammad_url=$(gp url $zammad_port)
    listmonk_url=$(gp url $listmonk_port)
    gitpod_config="$page_url:8080"
    echo "$gitpod_config" > config/gitpod
else
    page_url="http://localhost:$page_port"
    api_url="http://localhost:$page_port/api/doc/"
    devdocs_url="http://localhost:$devdocs_port"
    phpmyadmin_url="http://localhost:$phpmyadmin_port"
    zammad_url="http://localhost:$zammad_port"
    listmonk_url="http://localhost:$listmonk_port"
fi

echo
echo
echo "Some important informations:"
echo "  * Webpage:      $page_url"
echo "  * PHPMyAdmin:   $phpmyadmin_url"
if [ "${ZAMMAD:-false}" = "true" ]; then
    echo "  * Zammad:       $zammad_url"
fi
if [ "${LISTMONK:-false}" = "true" ]; then
    echo "  * listmonk:     $listmonk_url"
fi
echo
echo "Documentations:"
echo "  * API-DOCS:     $api_url"
echo "  * DEV-DOCS:     $devdocs_url"
echo
echo "Want to discuss with us, or help with some translations?"
echo "  * Slack:        https://slackin.yunity.org/"
echo "  * Translation:  https://hosted.weblate.org/projects/foodsharing/"
echo
echo
