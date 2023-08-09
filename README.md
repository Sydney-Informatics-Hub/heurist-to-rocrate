# Utility CLI Template

This is a template project which can be used to create utility tools.
It provides the project structure as well as some commonly used utility
classes.

## Add Commands

The template uses Symfony's console component to create console commands.
To add a command, create the command class under `src/Command` folder
and add the command in the `utility` file at the project root.

The template by default provides an example command called _Greetings_.

## Input & Output Directories

The template provides two directories for input & output files: `input`
and `output`. Any data file used as input can sit under the `input` folder.
At the same time, any output file can be output to the `output` folder.

There are two global constants called `DATA_INPUT_DIR` and `DATA_OUTPUT_DIR`
which can be easily used to get the path of these two directories.


## Heurist to RO-Crate

Arg1 : input path   Should be a path to the directory contains the Heurist xml files. Could be relative or absolute path.  e.g : "./input" or "C:\username/input/"
Args2: output path  Should be a file path contains the file name. Could be relative or absolute path.  e.g : "./output/metadata.json" or "C:\username/output/metadata.json"
Args3: database name (optional)  . Named option. e.g : --name="OMAA"
Args4: database description (optional) Named option e.g --description="Some description here..."

eg: php utility rocrate:create "./input" "output/metadata.json" --name="OMAA" --description="Some Description"
