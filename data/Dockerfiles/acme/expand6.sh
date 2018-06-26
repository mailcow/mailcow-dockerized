#!/bin/bash

##################################################################################
#
#  Copyright (C) 2017 Craig Miller
#
#  See the file "LICENSE" for information on usage and redistribution
#  of this file, and for a DISCLAIMER OF ALL WARRANTIES.
#  Distributed under GPLv2 License
#
##################################################################################


# IPv6 Address Expansion functions
#
# by Craig Miller   19 Feb 2017
#
# 16 Nov 2017 v0.93 - added CLI functionality


VERSION=0.93

empty_addr="0000:0000:0000:0000:0000:0000:0000:0000"
empty_addr_len=${#empty_addr}

function usage {
               echo " $0 - expand compressed IPv6 addresss "
         echo " e.g. $0 2001:db8:1:12:123::456 "
         echo " "
         echo " -t  self test"
         echo " "
         echo " By Craig Miller - Version: $VERSION"
         exit 1
           }

if [ "$1" == "-h" ]; then
  #call help
  usage
fi

#
# Expands IPv6 quibble to 4 digits with leading zeros e.g. db8 -> 0db8
#
# Returns string with expanded quibble

function expand_quibble() {
  addr=$1
  # create array of quibbles
  addr_array=(${addr//:/ })
  addr_array_len=${#addr_array[@]}
  # step thru quibbles
  for ((i=0; i< $addr_array_len ; i++ ))
  do
    quibble=${addr_array[$i]}
    quibble_len=${#quibble}
    case $quibble_len in
      1) quibble="000$quibble";;
      2) quibble="00$quibble";;
      3) quibble="0$quibble";;
    esac
    addr_array[$i]=$quibble 
  done
  # reconstruct addr from quibbles
  return_str=${addr_array[*]}
  return_str="${return_str// /:}"
  echo $return_str
}

#
# Expands IPv6 address :: format to full zeros
#
# Returns string with expanded address

function expand() {
  if [[ $1 == *"::"* ]]; then
    # check for leading zeros on front_addr
    if [[ $1 == "::"* ]]; then
      front_addr=0
    else
      front_addr=$(echo $1 | sed -r 's;([^ ]+)::.*;\1;') 
    fi
    # check for trailing zeros on back_addr
    if [[ $1 == *"::" ]]; then
      back_addr=0
    else
      back_addr=$(echo $1 | sed -r 's;.*::([^ ]+);\1;') 
    fi
    front_addr=$(expand_quibble $front_addr)
    back_addr=$(expand_quibble $back_addr)
    
    new_addr=$empty_addr
    front_addr_len=${#front_addr}
    back_addr_len=${#back_addr}
    # calculate fill needed
    num_zeros=$(($empty_addr_len - $front_addr_len - $back_addr_len - 1))

    #fill_str=${empty_addr[0]:0:$num_zeros}
    new_addr="$front_addr:${empty_addr[0]:0:$num_zeros}$back_addr"
    
    # return expanded address
    echo $new_addr
  else
    # return input with expandd quibbles
    expand_quibble $1
  fi
}

# self test - call with '-t' parameter
if [ "$1" == "-t" ]; then
  # add address examples to test
  expand fd11::1d70:cf84:18ef:d056
  expand 2a01::1
  expand fe80::f203:8cff:fe3f:f041
  expand 2001:db8:123::5
  expand 2001:470:ebbd:0:f203:8cff:fe3f:f041
  # special cases
  expand ::1
  expand fd32:197d:3022:1101::
  exit 1
fi

# allow script to be sourced (with no arguements)
if [[ $1 != "" ]]; then
  # validate input is an IPv6 address
  if [[ $1 == *":"* ]]; then
    expand $1
  else
    echo "ERROR: unregcognized IPv6 address $1"
    exit 1
  fi
fi
