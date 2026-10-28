# Municipality map centres

`municipality-centres.json` contains the seat/town centre of all 101 concelhos
in Aveiro, Viseu, Guarda, Coimbra, Castelo Branco and Leiria. These are approximate
public locations for listings without coordinates, not premises or field entrances.

Coordinate source: GeoNames, Portugal country dump, retrieved 2026-10-28:
https://download.geonames.org/export/dump/PT.zip

Data © GeoNames contributors, licensed under Creative Commons Attribution 4.0:
https://creativecommons.org/licenses/by/4.0/

Changes: selected municipality seats in the six districts, matched by district
and municipality name, normalised lookup keys and retained name, coordinates
and GeoNames record ID. Districts use GeoNames admin1 codes 02, 22, 11, 07, 06
and 13 respectively. Prefer PPLA/PPLA2 seats; otherwise use the matching populated
place in the same district. No third-party requests run when displaying the map.
