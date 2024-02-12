# Snom (Deskphone) Symcon module
## Description

This module is part of the [Snom Symcon library](https://github.com/symcon/Snom/tree/main).  
After configurating an instance of this module, the user of the Snom deskphone can control and visualize end devices of an automation system integrated in IP-Symcon.  

Pressing a function key of the Deskphone the user can, for example, control the lights, doors or blinds of a KNX system.  
The LEDS of the function keys can be used for visualizing the status of the different parts of a facility, for example, an LED on the phone turns red if an alarm is triggered.  

## Installing through the Module Store
Before being able to use the Snom module, it needs to be installed through the [Module Store](https://www.symcon.de/en/service/documentation/components/management-console/module-store/). The module can be found by searching for "Snom".

## Creating a Snom Deskphone instance
1. Navigate to the [Management Console](https://www.symcon.de/en/service/documentation/components/management-console/) of the IP-Symcon server
2. Open the ["Object tree"](https://www.symcon.de/en/service/documentation/components/management-console/object-tree/)  
3. Right click on the folder (Category) where you want to create the phone instance and click on "Add object" -> "Instance"
4. Search for "snom" and select the instance "Snom (Deskphone)".  
Give it a meaningful name, e.g., "Snom (Deskphone) D785 office" and click "OK"

## Instance configuration
```
Warning: For the configuration process, the phone must be connected in the same network as IP-Symcon
```
1. Type the IP address of the phone to be configured and press the button "Reachable?" to check if the phone is reachable
2. Apply the changes
3. If the phone web user interface is protected with credentials, you will be asked for typing them in.  
After doing it, apply the changes again

```
Warning: After adding function keys in the instance and applying the changes, the current settings for the edited function key(s) will be overwritten in the phone.  
For checking the current settings before, click on the button "See current function keys settings"
```
4. Click on "Add" for configuring a new function key.  
A form with following elements will pop up:
>> - _Function key_: Function key to setup:  
>>>>- P1...PXX for function keys of a deskphone  
>>>>- 1...XX for function keys of a [expansion module](https://www.snom.com/en/products/desk-phones/d7xx/snom-d7c/)
>>>>```
>>>>Warning: Only one expansion module is supported.
>>>>If several are connected, the settings of the one connected directly to the phone will be overwritten.
>>>>```
>> - _Label_: Text that will be displayed next to the selected function key
>> - _Color for status on_: Color of the LED when the assigned status variable is turned to on
>> - _Color for status off_: Color of the LED when the assigned status variable is turned to off
>> - _Fuctionality_:  
Each single function key can be configured for one of this modi:
>>>>- _Display status_: the value of the assigned variable will be displayed on the phone for 5 seconds when pressing the key
>>>>- _Update status LED_: the LED color will update depending on the value of the assigned status variable (e.g. signilizing an alarm)
>>>>- _Trigger action and update status LED_: the LED color will update depending on the value of the assigned status variable and when pressing the key, an action will be triggered on the selected target variable (e.g. LED signilizes an alarm and pressing the key will quit the alarm)
>> - _Target_: Target variable for the selected action
>> - _Action_: Action to be triggered on the target variable (e.g. toggle)
>> - _Use other variable for status LED_: If the status variable (LED color) must be different than the target variable (often in systems like KNX) turn this switch to on
>> - _Status variable_: The color of the LED will change depending on the value of this variable
5. After filling in the form, press "OK"
6. Add further function keys if needed
7. Apply the changes (current settings for edited function keys will be on the phone overwritten)
8. For changing parameters of a function key, click the gear wheel icon
9. For deleting a function key from the list, click the bin icon (It will not delete the function key settings on the phone)


```
Maintainer: Simón Golpe Varela
Support: simon.golpe@snom.com
Last update: January 2024
```
