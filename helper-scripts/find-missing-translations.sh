#!/usr/bin/env bash

# the english json file always has the complete list of language strings
# and keys.  this script will check each language and look for keys
# that are missing.
#
# jq needs to be installed, something like this will do it:
#    sudo apt install jq
#
# any parameters passed to the script will be treated as languages
# (try en kr zh).  to check multiple languages just add multiple
# parameters.  to check every language do not add any parameter.
#
# you will be asked if you can translate into that language and
# if you answer positively then you will be led through the process
# of adding the translation for each missing key.  if you answer
# negatively then a list of the missing keys will be displayed.
#
# if any translations were performed then a new file called
# lang.XX.json.edited will be created where XXX corresponds to the
# language code.  ideally this file should be forwarded back to the
# community by submitting a pull request here:
#     https://github.com/mailcow/mailcow-dockerized/pulls

# exit on errors
set -e

# a function that gets all the keys from a json file
# parameter is the language required (en, es, kr etc)
get_keys() {
    jq -r 'paths(scalars) | map(.|tostring)|join(" > ")' "lang.$1.json" | sort
}

# a function that asks a yes or no question
yes_or_no() {
    while true; do
        read -r -s -n1 -p "${1} (y/N)? " ANSWER
        ANSWER="${ANSWER:-N}"
        case "${ANSWER}" in
            [Yy]* ) echo "${ANSWER}"; return 0;;
            [Nn]* ) echo "${ANSWER}"; return 1;;
            * ) echo "Please answer yes or no.";;
        esac
    done
    return 1
}

# change to the language folder
cd /opt/mailcow-dockerized/data/web/lang || exit

# get an array of all the languages if we don't have any
LANGUAGES=( "$@" )
if [ "${#LANGUAGES[@]}" -eq 0 ]; then
    mapfile -t LANGUAGES < <( for NAME in *.json; do basename "${NAME}" .json | cut -d. -f2; done )
fi

# read the english file so we can re-use it
ENGLISH_KEYS=$( get_keys "en" )
ENGLISH_JSON=$( cat "lang.en.json" )

# process each of the languages
UPDATING=()  # array that holds all the languages that need updating
EDITED=1     # boolean that indicates whether an edit has been done
for LANGUAGE in "${LANGUAGES[@]}"; do
    # skip english
    if [[ "${LANGUAGE}" == "en" ]]; then continue; fi

    # alert about an unknown language
    if [ ! -f "lang.${LANGUAGE}.json" ]; then
        printf "\\n>> LANGUAGE ERROR: \"%s\" IS UNKNOWN!\\n" "${LANGUAGE}"
        continue
    fi

    # get the keys that do not exist in the current language
    readarray -t MISSING <<< "$( comm -23 <( echo "${ENGLISH_KEYS}" ) <( get_keys "${LANGUAGE}" ) )"

    # if there are no missing keys then skip the rest of this loop and
    # continue with the next language
    if [ "${#MISSING[@]}" -eq 0 ]; then continue; fi

    # ask the user if they can translate into the language
    if yes_or_no "Can you translate english into ${LANGUAGE}"; then
        # make a copy of the current json file to work on
        cp "lang.${LANGUAGE}.json" "lang.${LANGUAGE}.json.edited"

        # boolean to track whether a translation was made
        PROVIDED_TRANSLATION=1

        # alert the user how to skip a translation
        printf "\\nYou will now be asked to translate each of the %s missing keys.  If you do not know how to translate a phrase then leave it blank.\\n" "${#MISSING[@]}"

        # loop through each of the missing keys and attempt to get
        # translations from the user
        for LINE in "${MISSING[@]}"; do
            # the key names are separated by ' > ' when we create them in
            # get_keys(), create an array of the key names for this missing
            # group of keys.
            IFS=' > ' read -ra KEYS <<< "${LINE[@]}"

            # if KEYS has two members then we need to try to translate the key
            if [ "${#KEYS[@]}" -eq 2 ]; then
                # find the key that needs to be translated in english
                echo
                ENG_LINE=$( echo "${ENGLISH_JSON}" | jq -r --arg primary "${KEYS[0]}" --arg secondary "${KEYS[1]}" '.[$primary][$secondary]' )

                # ask the user to translate the key
                read -r -p "Translate \"${ENG_LINE}\": " TRANSLATION
                TRANSLATION=$( echo "${TRANSLATION}" | tr -d "'" | xargs )
                if [ ! -z "${TRANSLATION}" ]; then
                    if yes_or_no "Does \"${TRANSLATION}\" mean \"${ENG_LINE}\""; then
                        EDITED=0  # update the boolen to true as we have made an edit
                        PROVIDED_TRANSLATION=0  # update the boolean to true as we have sucessfully translated a phrase

                        # update the .json.edited file with the new translation
                        # sadly we cannot do this inline and require a
                        # temporary file
                        jq --indent 4 --sort-keys --arg primary "${KEYS[0]}" --arg secondary "${KEYS[1]}" --arg translation "${TRANSLATION}" '.[$primary][$secondary] = $translation' "lang.${LANGUAGE}.json.edited" > tmp.json && mv tmp.json "lang.${LANGUAGE}.json.edited"
                    fi
                fi
            else
                # just add a top level key to the .json.edited file
                # note - there are no 3rd level or higher keys (yet)
                jq --indent 4 --sort-keys --arg primary "${KEYS[0]}" '.[$primary] = {}' "lang.${LANGUAGE}.json.edited" > tmp.json && mv tmp.json "lang.${LANGUAGE}.json.edited"
            fi
        done
        # remove the .edited file if a translation was not provided
        if [ ! "${PROVIDED_TRANSLATION}" ]; then rm "lang.${LANGUAGE}.json.edited"; fi
    else
        # just output a list of the missing keys
        printf "\\n--\\nThese keys are missing in lang.%s.json:\\n" "${LANGUAGE}"
        printf "%s\\n" "${MISSING[@]}"
    fi
    UPDATING+=( "${LANGUAGE}" )
done

# clean up any files
if [ -f "tmp.json" ]; then
    rm "tmp.json"
fi

# talk to the user
if [[ "${#UPDATING[@]}" -eq 0 ]]; then
    echo "Every language is is up to date!"
else
    printf "\\nThese language(s) have/had missing keys: %s\\n" "${UPDATING[*]}"
fi

# mention if any files have been edited
if [ "${EDITED}" ]; then
    printf "\\nThe following files have been created and/or edited:\\n"
    for FILE in *.edited; do echo "${FILE}"; done
    NUMFILES=(*.edited)
    if [ "${#NUMFILES[@]}" -eq 0 ]; then echo "none"; fi
    printf "\\nPlease remember to give back to mailcow and share any edited language files on github here: https://github.com/mailcow/mailcow-dockerized/pulls\\n\\n"
fi
