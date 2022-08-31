#!/usr/bin/env bash

set -e

function join_by { local IFS="$1"; shift; echo "$*"; }

IFS=' ' read -r -a PAGES <<< "$PAGES"

echo "SITE: ${SITE}"
echo "PAGES: ${PAGES}";

declare -A pages_map
declare -a json

if [ "${#PAGES}" -eq 0 ]; then
    echo "Load only index.php"
    pages_map["index.php"]="index.php"
else
    contains=0

    for arg in "${PAGES[@]}"
    do
        if [ "$arg" = "${SITE}" ]; then
            continue
        elif [ "$arg" = "index.php" ]; then
            contains=1
        elif [ "$arg" = "index.html" ]; then
            contains=1
        elif [ "$arg" = "/" ]; then
            contains=1
        fi
        pages_map[$arg]="${arg}"
    done

    if [ $contains -eq 0 ]; then
        echo "Add index.php"
        pages_map["index.php"]="index.php"
    fi
fi

for PAGE in "${!pages_map[@]}"
do
    echo "Load ${pages_map[$PAGE]}"
    if [ "$PAGE" != "$SITE" ]; then
        filename=${pages_map[$PAGE]//[^A-Za-z0-9]/_}
        curl -o ./${filename} "https://${SITE}/${pages_map[$PAGE]}"

        dirname="${SITE}/${pages_map[$PAGE]}"
        dirname=${dirname//[^A-Za-z0-9]/_}

        if [ "$TRANSPORT" = "ftp" ]; then
            curl -k --ftp-ssl -u $FTP_USER:$FTP_PASSWORD "ftp://${TRACKER_HOST}:21/${TRACKER_LAND_DIR}${dirname}/index.html" -T ./${filename} --ftp-create-dirs --
        elif [ "$TRANSPORT" = "ssh" ]; then
            echo mkdir ${dirname} | sftp ${TRACKER_USER}@${TRACKER_HOST}:${TRACKER_LAND_DIR}
            echo put ./${filename} ${dirname}/index.html | sftp ${TRACKER_USER}@${TRACKER_HOST}:${TRACKER_LAND_DIR}
        else
            echo "Invalid transport"
            exit 1
        fi

        pages_map[$PAGE]="${dirname}/index.html"
        json+=" \"${PAGE}\":\"${pages_map[$PAGE]}\""
    fi
done

json_string=`join_by , ${json}`
json_string="{${json_string}}"

echo $json_string;

output=`php7.2 ./createCampaingn.php $SITE "${lands_json}"`

echo output