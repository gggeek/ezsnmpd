#!/bin/sh

# example of a shell script to be used to allow the snmpd daemon
# to invoke the php snmpd agent.
# Useful to set up execution of the agent script in the proper directory.

EZPDIR=`dirname $0`

cd $EZPDIR/../../../..
php extension/ezsnmpd/bin/php/ezSNMPagent.php $*
