#!/usr/bin/env bash

set -o errexit
dir=$(dirname "$0")
source "$dir"/__common.sh

## Environment port mapping for the url creation

page_port=18080 #nginx (dev)
phpmyadmin_port=18081
devdocs_port=13000
zammad_port=18087
listmonk_port=18088


page_url="http://localhost:$page_port"
api_url="http://localhost:$page_port/api/doc/"
devdocs_url="http://localhost:$devdocs_port"
phpmyadmin_url="http://localhost:$phpmyadmin_port"
zammad_url="http://localhost:$zammad_port"
listmonk_url="http://localhost:$listmonk_port"
bluespice_url="http://localhost:18085"

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
if [ "${BLUESPICE:-false}" = "true" ]; then
    echo "  * Bluespice:    $bluespice_url"
    echo "  * Initial Bluespice Admin Password: $(cat ./tmp/bluespice/wiki/initialAdminPassword)"
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
