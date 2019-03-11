# Muutosloki
Partio ohjelman muutosloki

Perustuu projektiin [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)

## [Julkaisematta]

### Lisätty
- PO-292: Wp-cli:llä ajettava versio importista
- PO-360: Importer poistamaan importatut postaukset joita ei ole enään backendissä
- PO-237: Ikonin haku backendistä
- PO-334: Automaattinen haku jos käyttäjä kirjoittaa hakukenttään, 500ms viiveellä

### Muutettu
- PO-326: Korjattu importerin postmetan importtaus ja viimeksi muokattu kentän päivitys
- PO-292: Nopeutettu importerin sivujen importtausta
- PO-267: Classic editor plugini wp päivityksen myötä
- PO-288: Teeman värit manifest tiedostoon ja meta tagiin
- PO-267: Näytetään kun haku on käynnissä
- PO-351: Optimoitu redikseen menevää dataa
- PO-269: Parannettu menu logiikkaa
- PO-349: Otetaan "tasks" pois haku filttereistä
- PO-310: Vaihdettu SEO kuvan haku logosta, jos sellainen on
- PO-336: Näytetään hakutuloksia vain jos on hakusana tai filtereitä
- PO-343: Näytetään "{num} Tulosta" teksti vain jos on tehty haku

### Korjattu
- PO-320: Yläreunan kuvakaruselli
- PO-293: Vinkkien järjestäminen
- PO-350: Tehdään tyhjentämisen jälkeen haku vain jos jotain muuttui
- PO-300: Haun tyhjennä fillterit painike

## [1.2.0] - 2018-09-05

### Muutettu
- PO-287: Laitettu takaisin title kenttä vinkin lisäykseen
- PO-299: Piilotettu haun rajaustapa toistaiseksi
- PO-301: Haun filttereiden taustaväri
- PO-308: Piilotettu IE:n tekstikentän X painike
- PO-288: Päivitetty jQuery ja siirretty se webpackillä compilattavaksi
- PO-260: Kaikki frontin käännökset haetaan pof backendistä

### Lisätty
- PO-270: Sisältöjen jakotoiminto kopioimalla linkki leikepöydälle
- PO-288: Teeman värit manifest tiedostoon ja meta tagiin

### Korjattu
- PO-324: Hakutuloksien määrä
- PO-305: Etusivun hakukentän mobiilityylit
- PO-322: Hakutuloksien filtteröinti mobiilissa
- PO-298: Vanhojen filttereiden toiminta uuden tekstihaun jälkeen
- PO-302: Vanhan haun teko filtereiden muuttamisen jälkeen
- PO-323: Filtereiden checkboxien mobiilityylit
- PO-288: Laitettu sivun logolle alt attribuutti
- PO-288: Kielinavigaation rakenteen korjaus

## [1.1.1] - 2018-08-14

### Korjattu
- PO-289: Lisätty puuttuvat .hide-for-small-only & .show-for-small-only classit

## [1.1.0] - 2018-08-07

### Lisätty
- PO-284: manifest.json käsittely
- PO-283: meta descriptionin generointi
- PO-275: Image helperit
- PO-276: Image helperit backend kuville
- PO-279: Font swap asetukset

### Muutettu
- PO-284: Sivuston ikonin hallinta wp adminiin
- PO-277: Vaihdettu assettien compilaus webpackiin
