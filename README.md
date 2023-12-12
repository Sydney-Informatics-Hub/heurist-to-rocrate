# Heurist to RO-Crate tool

This repository contains the command-line tools to archive a [Heurist](https://heuristnetwork.org/) database in 
[RO-Crate](https://www.researchobject.org/ro-crate/).

## System requirements

- PHP 8.0+ (with `zip` extension enabled).
- Composer

## Installation

Clone this repository and run `composer install` in the root directory.

## Commands

### Create RO-Crate

Convert a Heurist archive to an RO-Crate archive.

This command reads in a Heurist archive which contains the Heurist data structure and records. It converts the Heurist
data in the format of RO-Crate metadata. It also includes the uploaded files from Heurist in the RO-Crate archive.

```shell
php utility rocrate:create HEURIST_DATABASE_NAME HEURIST_ARCHIVE_DIR_PATH OUTPUT_FILE_PATH [--heurist-name=HEURIST_NAME] [--heurist-description=HEURIST_DESCRIPTION] [--configuration=CONFIGURATION_FILE_PATH]
```

#### Arguments

- `HEURIST_DATABASE_NAME`: The machine name of the Heurist database.
- `HEURIST_ARCHIVE_DIR_PATH`: The path to the extracted Heurist archive directory (without tailing slash).
- `OUTPUT_FILE_PATH`: The path to the output RO-Crate archive file (`.zip`).

#### Options

- `--heurist-name`: The human-readable name of the Heurist database.
- `--heurist-description`: The description of the Heurist database.
- `--configuration`: The path to the configuration file. It can be the zipped RO-Crate file containing the configurations
  or just the `ro-crate-metadata.json` of the configuration RO-Crate. See below for more details about the configuration
  file.

#### Example

```shell
php utility rocrate:create my_database /path/to/my_database my_database_ro_crate.zip --heurist-name="My Database" --heurist-description="This is my database." --configuration=/path/to/configuration.zip
```

## Configurations

Configurations of the conversion process can be specified in a dedicated RO-Crate file. The RO-Crate metadata can define
the desired mapping strategy of the Heurist data to the RO-Crate metadata. Mapping relationships can be created as 
entities in the RO-Crate metadata, which specify the source object types from Heurist and target object types in the
target RO-Crate.

The configuration RO-Crate contains some pre-defined classes and properties to be used to describe the Heurist data 
structure.

### Class types

#### Entity class types

Instances of these classes can be used as mapping sources.

- `_RecordType`: describes a record type from Heurist. It could contain the information about that record type including 
  things like the Heurist ID.
- `_Vocabulary`: describes a taxonomy vocabulary from Heurist.

#### Field class types

Instances of these classes can be used as mapping sources.

- `_BaseField`: describes a base field from Heurist.
- `_Field`: describes a field from Heurist.

#### Taxonomy attribute class types

Terms from a Heurist vocabulary can be mapped to another class instead of the default `DefinedTermSet` and `DefinedTerm`
entities in RO-Crate. Similar to the relationship between Heurist record types and base fields/fields, Heurist taxonomy
attributes can be described in the mapping RO-Crate which later can be used as mapping sources. As taxonomy attributes
from Heurist don't have ID, these classes are used to describe the commonly used taxonomy attributes.

- `_VocabularyTermLabel`: describes the label attribute on Heurist vocabulary.
- `_VocabularyTermDescription`: describes the description attribute on Heurist vocabulary.
- `_VocabularyTermCode`: describes the term code attribute on Heurist vocabulary.

#### Utility class types

These are the classes with other purposes such as data value conversion in the mapping RO-Crate.

- `_Function`: describes a value conversion function applying to the source data value.

### Property types

- `_sourceType`: can be used on a mapping entity to identify the entity type from Heurist. Can be an instance of class
  `_RecordType` or `_Vocabulary`.
- `_sourceIdentifier`: used to specify the Heurist ID.
- `_valueFunction`: used to define a value conversion function to a `_BaseField` or `_Field`.
- `_name`: this is a workaround of mapping standard schema.org `name` property. The standard `name` property can only
  use plain text as its value. However, in the mapping process, the `name` property is often mapped to a based field
  from Heurist. Tools like Describo/Crato will handle this badly if it uses a reference as its value. Therefore, a
  custom property `_name` is created to be used for mapping, and leave the standard `name` property only for displaying
  user-friendly labels.

## Descriptive Entities

Descriptive entities are the RO-Crate entities describe the types of entities from Heurist. These entities can be
created with the custom class types and properties.

### Record Types

An entity with the class type `_RecordType` describing a record type from Heurist. It should have the property
`_sourceIdentifier` to identify its ID in Heurist.

```json
{
    "@id": "#record_type_omaa_newspaper",
    "@type": "_RecordType",
    "name": "OMAA Newspaper",
    "description": "OAMA newspaper",
    "_sourceIdentifier": "107"
}
```

### Vocabularies

An entity with the class type `_Vocabulary` describing a taxonomy vocabulary from Heurist. It should have the property
`_sourceIdentifier` to identify its ID in Heurist.

```json
{
    "@id": "#vocabulary_omaa_language",
    "@type": "_Vocabulary",
    "name": "OMAA Language",
    "description": "",
    "_sourceIdentifier": "9476"
}
```

### Base Fields

An entity with the class type `_BaseField` describing a base field from Heurist. It should have the property
`_sourceIdentifier` to identify its ID in Heurist. It can also have property `_valueFunction` to specify the value
conversion on the value from the base field.

```json
{
    "@id": "#base_field_name_or_title",
    "@type": "_BaseField",
    "name": "Name or Title",
    "description": "The main name or title for the object",
    "_sourceIdentifier": "1"
}
```

### Fields

An entity with the class type `_Field` describing a field from Heurist. It should have the property `_sourceIdentifier`
to identify its ID in Heurist. It can also have property `_valueFunction` to specify the value conversion on the value
from the base field.

```json
{
    "@id": "#field_access_rights",
    "@type": "_Field",
    "name": "Access Rights",
    "description": "Information about who can access the resource or an indication of its security status.",
    "_sourceIdentifier": "1101",
    "_valueFunction": {"@id": "#function_to_text"}
}
```

### Mapping Entities

Mapping Entities define the mapping from Heurist entities such as records, vocabulary terms to RO-Crate entities.

A mapping entity looks similar to the entities created in the output RO-Crate. The difference in the mapping entity is
that the properties use the descriptive entities as the values instead of the actual field value to indicate the
property data source. The mapping entity will also have the `_sourceType` property to identify the source of the entity
type.

```json
{
    "@id": "#record_place",
    "@type": "Place",
    "_sourceType": {"@id": "#record_type_place"},
    "name": "Place Record",
    "_name": {"@id": "#base_field_name_or_title"},
    "geo": {"@id": "#base_field_mappable_location"}
}
```

In the example, we map the Heurist record type called "Place" (from `_sourceType`) to the standard schema.org type
"Place" (from `@type`). Then map the base field "Name or Title" in Heurist record type "Place" to the standard
schema.org property `name`. And map the base field "Mappable Location" in Heurist record type "Place" to the standard
schema.org property `geo`.

Note the `@type` and property names mapped in the mapping entity don't have to be standard schema.org classes and
properties. Custom class types and properties can be also defined in the mapping RO-Crate and mapped in the mapping
entities.

### Starter template

The following is a starter template of the configuration RO-Crate metadata, which have pre-defined classes and 
properties set up. To work with the starter template, start by describing the Heurist entities and then create mapping
entities.

```json
{
    "@context": [
        "https://w3id.org/ro/crate/1.1/context",
        {
            "_RecordType": "https://w3id.org/ro/terms/heurist-mapping#_RecordType",
            "_Vocabulary": "https://w3id.org/ro/terms/heurist-mapping#_Vocabulary",
            "_BaseField": "https://w3id.org/ro/terms/heurist-mapping#_BaseField",
            "_Field": "https://w3id.org/ro/terms/heurist-mapping#_Field",
            "_VocabularyTermLabel": "https://w3id.org/ro/terms/heurist-mapping#_VocabularyTermLabel",
            "_VocabularyTermDescription": "https://w3id.org/ro/terms/heurist-mapping#_VocabularyTermDescription",
            "_VocabularyTermCode": "https://w3id.org/ro/terms/heurist-mapping#_VocabularyTermCode",
            "_Function": "https://w3id.org/ro/terms/heurist-mapping#_Function",
            "_name": "https://w3id.org/ro/terms/heurist-mapping#_name",
            "_sourceType": "https://w3id.org/ro/terms/heurist-mapping#_sourceType",
            "_valueFunction": "https://w3id.org/ro/terms/heurist-mapping#_valueFunction",
            "_sourceIdentifier": "https://w3id.org/ro/terms/heurist-mapping#_sourceIdentifier"
        }
    ],
    "@graph": [
        {
            "@type": "CreativeWork",
            "@id": "ro-crate-metadata.json",
            "conformsTo": {
                "@id": "https://w3id.org/ro/crate/1.1"
            },
            "about": {
                "@id": "./"
            }
        },
        {
            "@id": "./",
            "@type": "Dataset",
            "name": "Data mapping of Heurist to RO-Crate",
            "description": "Provides the mapping structure from Heurist to RO-Crate"
        },
        {
            "@id": "#vocabulary_term_label",
            "@type": "_VocabularyTermLabel",
            "name": "Term Label",
            "description": "The label of the vocabulary term"
        },
        {
            "@id": "#vocabulary_term_description",
            "@type": "_VocabularyTermDescription",
            "name": "Term Description",
            "description": "The description of the vocabulary term"
        },
        {
            "@id": "#vocabulary_term_code",
            "@type": "_VocabularyTermCode",
            "name": "Term Code",
            "description": "The code of the vocabulary term"
        },
        {
            "@id": "#function_to_text",
            "@type": "_Function",
            "name": "to_text",
            "description": "Convert to text"
        },
        {
            "@id": "https://w3id.org/ro/terms/heurist-mapping#_RecordType",
            "@type": "rdfs:Class",
            "rdfs:label": "_RecordType",
            "rdfs:comment": "The record type from Heurist"
        },
        {
            "@id": "https://w3id.org/ro/terms/heurist-mapping#_Vocabulary",
            "@type": "rdfs:Class",
            "rdfs:label": "_Vocabulary",
            "rdfs:comment": "The vocabulary from Heurist"
        },
        {
            "@id": "https://w3id.org/ro/terms/heurist-mapping#_BaseField",
            "@type": "rdfs:Class",
            "rdfs:label": "_BaseField",
            "rdfs:comment": "The base field from Heurist"
        },
        {
            "@id": "https://w3id.org/ro/terms/heurist-mapping#_Field",
            "@type": "rdfs:Class",
            "rdfs:label": "_Field",
            "rdfs:comment": "The field from Heurist"
        },
        {
            "@id": "https://w3id.org/ro/terms/heurist-mapping#_VocabularyTermLabel",
            "@type": "rdfs:Class",
            "rdfs:label": "_VocabularyTermLabel",
            "rdfs:comment": "The label of the vocabulary term"
        },
        {
            "@id": "https://w3id.org/ro/terms/heurist-mapping#_VocabularyTermDescription",
            "@type": "rdfs:Class",
            "rdfs:label": "_VocabularyTermDescription",
            "rdfs:comment": "The description of the vocabulary term"
        },
        {
            "@id": "https://w3id.org/ro/terms/heurist-mapping#_VocabularyTermCode",
            "@type": "rdfs:Class",
            "rdfs:label": "_VocabularyTermCode",
            "rdfs:comment": "The code of the vocabulary term"
        },
        {
            "@id": "https://w3id.org/ro/terms/heurist-mapping#_Function",
            "@type": "rdfs:Class",
            "rdfs:label": "_Function",
            "rdfs:comment": "A function to excute when writting values"
        },
        {
            "@id": "https://w3id.org/ro/terms/heurist-mapping#_name",
            "@type": "rdf:Property",
            "rdfs:label": "_name",
            "rdfs:comment": "A property used to map to the standart schema.org name property."
        },
        {
            "@id": "https://w3id.org/ro/terms/heurist-mapping#_sourceType",
            "@type": "rdf:Property",
            "rdfs:label": "_sourceType",
            "rdfs:comment": "An entity type from the data source"
        },
        {
            "@id": "https://w3id.org/ro/terms/heurist-mapping#_valueFunction",
            "@type": "rdf:Property",
            "rdfs:label": "_valueFunction",
            "rdfs:comment": "Functions to apply on the value"
        },
        {
            "@id": "https://w3id.org/ro/terms/heurist-mapping#_sourceIdentifier",
            "@type": "rdf:Property",
            "rdfs:label": "_sourceIdentifier",
            "rdfs:comment": "An ID from the data source"
        }
    ]
}
```

## TO-DO

- [ ] Read the Heurist archive directly from the archive (`.zip`) file. (Currently, Heurist exports incomplete archives.
  Some files need to be manually copied from the Heurist database. Will implement this once the Heurist team fixes 
  this.)
- [x] Read the configuration directly from the RO-Crate archive (`.zip`) file.
- [x] Output complete whole RO-Crate archive (`.zip`) file from the `rocrate:create` command, which includes the
 uploaded files from Heurist as well.
