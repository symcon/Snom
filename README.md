# Snom Symcon library
## Description
Librarary for integrating [Snom IP phones](https://www.snom.com/en/products/) in the [Symcon](https://www.symcon.de/en/product/) building automation ecosystem.

Different building automation systems (e.g. [KNX](https://www.knx.org/knx-en/for-your-home/benefits/end-customers/), [Modbus](https://modbus.org/about_us.php), [Shelly](https://www.shelly.com/en)), applications (e.g. Spotify) or even your Tesla car can be controlled or monitored from your Snom IP phone.

## Requirements
[IP-Symcon](https://www.symcon.de/en/downloads/) must run on one of this devices:
- [SymBox](https://www.symcon.de/en/shop/symbox/)
- Linux (Debian based)
- Docker
- MacOS
- Windows

## Limitations
- Supported Snom devices: [Snom D335](https://www.snom.com/de/produkte/tischtelefone/d3xx/snom-d335/), [Snom D385](https://www.snom.com/de/produkte/tischtelefone/d3xx/snom-d385/), [Snom D735](https://www.snom.com/de/produkte/tischtelefone/d7xx/snom-d735/), [Snom D785](https://www.snom.com/de/produkte/tischtelefone/d7xx/snom-d785/), [Snom D7C](https://www.snom.com/en/products/desk-phones/d7xx/snom-d7c/), [Snom D7](https://www.snom.com/de/produkte/tischtelefone/d7xx/snom-d7-w/), [Snom D3](https://www.snom.com/de/produkte/tischtelefone/d3xx/snom-d3/)
- Only boolean variables supported (on/off)

## Library modules
- [Snom (Deskphone)](https://github.com/symcon/Snom/tree/main/SnomDeskphone)

```
Maintainer: Simón Golpe Varela
Support: simon.golpe@snom.com
Last update: January 2024
```