#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd $DIR

echo "-----------------------------------------"
cd 
cd cydynni
branch="$(git rev-parse --abbrev-ref HEAD)"
commit="$(git rev-parse HEAD)"
echo "cydynni:"$branch":"$commit
cd
