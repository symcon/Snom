# Snom (Page) module
## Description
It generates and serves a Snom minibrowser page (XML) with a message and a timeout as content.  
It provides the URLs for rendering the page in the local or in a remote Snom phone.

## Instance configuration
- Phone IP:  
IP address of the remote phone where the minibrowser page must be rendered
- Local IP:  
IP address of the machine where SymOS is running (e.g. IP address of the Symbox) 
- Message:  
Message to render on the phone
- Page timeout:  
Time that the page must be rendered (miliseconds)
- URL for local rendering:  
Action URL to setup in a Snom phone function key for rendering the page in the same phone
- URL for remote rendering:  
Action URL to setup in a Snom phone function key or in your software application for rendering the page in the given phone (Phone IP field)

