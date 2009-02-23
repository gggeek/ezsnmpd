@echo off

REM example of a batch file to be used on windows to allow the snmpd daemon
REM to invoke the php snmpd agent
REM path to php executable and to its ini file are given in this case
REM (ini file for cli is usually different from the one used for the webserver)

d:\php5\php -c d:\php5 D:\htdocs\ezp\clients\femar-mondadori\eZ\extension\ezsnmpd\ezSNMPagent.php
