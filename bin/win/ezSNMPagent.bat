@echo off

REM example of a batch file to be used on windows to allow the snmpd daemon
REM to invoke the php snmpd agent.
REM Path to php executable and to its ini file are given in this case
REM (ini file for cli is usually different from the one used for the webserver)

set _phpdir_=d:\php5

%_phpdir_%\php -c %_phpdir_% %~dp0..\php\ezSNMPagent.php %*
