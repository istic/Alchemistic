#!/bin/bash

# This script will update the .env file with the ngrok URLs
# It will also backup the .env file before making any changes
# The backup will be named .env.<timestamp>.bak
# The script will also create a .env.ngrok file to store the ngrok URLs
# This file will be deleted after the .env file is updated

trap 'rm -f .env.ngrok; exit 1' EXIT HUP INT TERM

ENVFILE=".env"
SCRIPTNAME=$(basename "$0")

if hash ts 2>/dev/null; then
    exec > >(ts '[%Y-%m-%d %H:%M:%S]' | tee -a "storage/logs/$SCRIPTNAME.log") 2>&1
else
    exec > >(tee -a "storage/logs/$SCRIPTNAME.log") 2>&1

    echo -----------------------------
    date
    echo -----------------------------
fi

function is_gnu_sed() {
    sed --version >/dev/null 2>&1
}

if is_gnu_sed; then
    echo "Found native GNU sed"
    SED="sed"
else
    if hash gsed 2>/dev/null; then
        echo "Using GNU sed (gsed)"
        SED="gsed"
    else
        echo "Non-GNU sed"
        echo "Please install GNU sed: brew install gnu-sed"
        exit 1
    fi
fi

curl --silent --show-error http://127.0.0.1:4040/api/tunnels |
    jq '.tunnels[] | { ("NGROK_" + .name + "_URL"|ascii_upcase):  (.public_url|sub("[a-z]+:\/\/";"";"i")) } ' |
    jq -rs '.[] | to_entries[] | [.key,.value] | join("=")' \
        >.env.ngrok

find . -name "$ENVFILE.*.bak" -cmin +5 -delete # delete backups older than 5 days
cp $ENVFILE "$ENVFILE.$(date +%s).bak"              # backup the .env file

if [[ ! -f $ENVFILE ]]; then
    echo "No $ENVFILE found, creating a new one."
    touch $ENVFILE
fi

# for i in $(cat .env.ngrok); do
while read -r i; do
    echo "Checking $i"
    if ! grep -q "$i" $ENVFILE; then
        IFS='=' read -r -a thisline <<<"$i"
        $SED -i "s@^${thisline[0]}=.*@${thisline[0]}=${thisline[1]}@" $ENVFILE
        echo "Replaced ${thisline[0]}=${thisline[1]} to $ENVFILE"
    fi
    if ! grep -q "$i" $ENVFILE; then
        echo "... that didn't work. Adding it instead."
        echo "${thisline[0]}"="${thisline[1]}" >>$ENVFILE
        echo "Added ${thisline[0]}=${thisline[1]} to $ENVFILE"
    fi
done < .env.ngrok

if [[ $NGROK_DELTA_VITE_URL ]] && [[ -f public/hot ]]; then
    echo updating public/hot
    echo https://"$NGROK_DELTA_VITE_URL" >public/hot
fi

rm -f .env.ngrok
