# Snom Symcon library

## Description
Librarary for integrating [Snom IP phones](https://www.snom.com/en/products/) in the [Symcon](https://www.symcon.de/en/product/) building automation ecosystem.  

That means, that different building automation systems (e.g. [KNX](https://www.knx.org/knx-en/for-your-home/benefits/end-customers/), [Modbus](https://modbus.org/about_us.php), [Shelly](https://www.shelly.com/en)), applications (e.g. Spotify) or even your Tesla car can be controlled or monitored from your Snom IP phone.

## Requirements
The [SymconOS](https://www.symcon.de/en/downloads/) must run in one of this devices:
- [Symbox](https://www.symcon.de/en/shop/symbox/)
- Linux machine (Debian based)
- Windows machine

## Limitations
- Supported Snom devices: [Snom D335](https://www.snom.com/de/produkte/tischtelefone/d3xx/snom-d335/), [Snom D385](https://www.snom.com/de/produkte/tischtelefone/d3xx/snom-d385/), [Snom D735](https://www.snom.com/de/produkte/tischtelefone/d7xx/snom-d735/), [Snom D785](https://www.snom.com/de/produkte/tischtelefone/d7xx/snom-d785/)
- Only boolean variables supported (on/off)

## <span style="color:red">Warnings <span>
- <span style="color:red">The modules of this library work only with HTTP.</span>  
If the PBX where the phone is registered or the user changes the phone server settings to allow only HTTPS, the module will not work

## Library modules
- [Snom (Deskphone)](https://gitlab.com/simon.golpe/snom_symcon/-/blob/main/SnomDeskphone/README.md)  

```
Maintainer: Simón Golpe Varela  
Support: simon.golpe@snom.com
Last update: December 2023
```