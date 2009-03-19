<?php /*

[MIB]

# The base prefix for all the OIDs we answer for.
# Always keeping within within iso.org.dod.internet.private.enterprise (1.3.6.1.4.1) is a good idea
# .eZ Systmes (.33120) - see http://www.iana.org/assignments/enterprise-numbers
# .eZ Publish (.1)
Prefix=1.3.6.1.4.1.33120.1

# List of php classes delegated to answer GET/SET responses
# each one of those takes care of a list of OIDs, that form the overall eZ Publish MIB
SNMPHandlerClasses[]
SNMPHandlerClasses[]=eZsnmpdSettingsHandler
SNMPHandlerClasses[]=eZsnmpdStatusHandler
SNMPHandlerClasses[]=eZsnmpdInfoHandler
# Used for test/development
SNMPHandlerClasses[]=eZsnmpdTestHandler

/* ?>