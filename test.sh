#!/bin/bash
source mailcow.conf
SOLR_OPTIONS=(
  "SOLR_HEAP"
  "SOLR_PORT"
  "SKIP_SOLR"
  )
remove_solr_config_options() {
  sed -i --follow-symlinks '$a\' mailcow.conf
  for option in "${SOLR_OPTIONS[@]}"; do
  if [[ $option == "SOLR_HEAP" ]]; then
    if grep -q "${option}" mailcow.conf; then
      echo "Replacing SOLR_HEAP with \"${option}\" in mailcow.conf"
      sed -i '/# Solr heap size in MB\b/c\# Dovecot Indexing (FTS) Process heap size in MB, there is no recommendation, please see Dovecot docs.' mailcow.conf
      sed -i '/# Solr is a prone to run\b/c\# Flatcurve is replacing solr as FTS Indexer completely. It is supposed to be much more efficient in CPU and RAM consumption.'  mailcow.conf
      sed -i 's/SOLR_HEAP/FTS_HEAP/g' mailcow.conf
    fi
  fi
  if [[ $option == "SKIP_SOLR" ]]; then
    if grep -q "${option}" mailcow.conf; then
      echo "Replacing $option in mailcow.conf with SKIP_FLATCURVE"
      sed -i '/\bSkip Solr on low-memory\b/c\# Skip Flatcurve (FTS) on low-memory systems or if you simply want to disable it.' mailcow.conf
      sed -i '/\bSolr is disabled by default\b/d' mailcow.conf
      sed -i '/\bDisable Solr or\b/d' mailcow.conf
      sed -i 's/SKIP_SOLR/SKIP_FLATCURVE/g' mailcow.conf
    fi
  fi
  if [[ $option == "SOLR_PORT" ]]; then
    if grep -q "${option}" mailcow.conf; then
      echo "Removing ${option} in mailcow.conf"
      sed -i '/\bSOLR_PORT\b/d' mailcow.conf
    fi
  fi
  done

  solr_volume=$(docker volume ls -qf name=^${COMPOSE_PROJECT_NAME}_solr-vol-1)
  if [[ -n $solr_volume ]]; then
    echo -e "\e[34mSolr has been replaced within mailcow since 2024-XX.\e[0m"
    sleep 1
    echo -e "\e[34mTherefore the volume $solr_volume is unused.\e[0m"
    sleep 1
    read -r -p "Would you like to remove the $solr_volume? " response
    if [[ "$response" =~ ^([yY][eE][sS]|[yY])+$ ]]; then
      echo -e "\e[33mRemoving $solr_volume...\e[0m"
      docker volume rm $solr_volume
      if [[ $? != 0 ]]; then
        echo -e "\e[31mCould not remove the volume... Please remove it manually!\e[0m"
      else
        echo -e "\e[32mSucessfully removed $solr_volume!\e[0m"
      fi
    else
      echo "Ok! Not removing $solr_volume then."
      echo "Once you decided on removing the volume simply run docker volume rm $solr_volume to remove it manually."
      echo "This can be done anytime. mailcow does not use this volume anymore."
    fi
  fi  
}

remove_solr_config_options