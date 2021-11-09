codingdavinci
=============
- The code for the web-application can be found in php/
- data.sql is a MySQL-database including the List, Publication and additional Person and Place

## Database Schema ##

### list ###
Data from http://www.berlin.de/rubrik/hauptstadt/verbannte_buecher/verbannte-buecher.json

- row (1 based to connect the entry to the original JSON-entry)
- entry (the original entry for reference)

The following fields are taken verbatim from the original list

- title
- ssFlag
- ...

Enhanced with additional information
- completeWork (1: Gesamtwerk, 2: Teile des Gesamtwerkes)

and temporary properties until Publication.gnd and Person.gnd are set properly

- titleGnd VARCHAR(511) NULL,
- authorGnd  VARCHAR(511) NULL,
- authorGnd2 VARCHAR(511) NULL,

### publication ###
Data about the publications, property-names from the GND-Linked Data Representation and Bibo-properties (https://bibotools.googlecode.com/svn/bibo-ontology/trunk/doc/classes/Document___-538479979.html)

- title
- otherTitleInformation (Untertitel)
- placeOfPublication
- publisher
- publisherId
- publicationStatement # Place : Publisher
- extent (see http://metadataregistry.org/schemaprop/show/id/1995.html, <isbd:P1053>16 S.</isbd:P1053>)
- isPartOf (e.g. Arbeiter-Gesundheits-Bibliothek)
- bibliographicCitation (e.g. Arbeiter-Gesundheits-Bibliothek ; H. 11)
- issued
- parentId (to related different editions of a Publication, e.g. re-editions or translations of the original work on the List)
- listRow  (reference to List.row)
- listEdition (marker to indicate if this is the entry specified in firstEdition* or secondEdition* properties)

Norm-data identifiers
- gnd
- oclc

### person ###
- forename
- surname
- preferredName (Nachname, Vorname; z.B. Wolf, Victoria)
- variantNames (Wolf, Victoria Trude; Wolf, Victoria T.; Wolf, Viktoria; Wolff, Victoria; Wolff, Viktoria);
- gender (female, male)
- listRow  (reference to List.row)
- gnd
- viaf
- wikidata

### publicationperson ###
- publication_id
- person_id
- role ENUM ('aut', 'edt', 'trl') # see http://www.loc.gov/marc/relators/relaterm.html
- person_ord INT NOT NULL 0 # for ordering multiple person
- publication_ord INT NULL  # for ordeirng multiple publication per person by issued-date


## Third party API ##
- Person nach Name:
	- Bsp: http://lobid.org/gnd/search?q=preferredName%3Awolf+victoria&filter=type%3APerson&format=json
- Publikation nach Titelstichwort, Erscheinungsjahr und Autor
	- Bsp: https://lobid.org/resources/search?q=&issued=1907&agent=Ignaz+Zadek&name=Frauenleiden
- Eingefügte Daten nach GND:
	- Bsp: http://hub.culturegraph.org/entityfacts/124963463
