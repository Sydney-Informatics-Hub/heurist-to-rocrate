# Heurist to RO-Crate tool

This repository contains the command-line tools to archive a [Heurist](https://heuristnetwork.org/) database in 
[RO-Crate](https://www.researchobject.org/ro-crate/).

## System requirements

- PHP 8.0+ (with `zip` extension enabled).
- Composer

## Installation

Clone this repository and run `composer install` in the root directory.

## Commands

TBD

## Configuration

TBD

## TO-DO

- [ ] Read the Heurist archive directly from the archive (`.zip`) file. (Currently, Heurist exports incomplete archives.
  Some files need to be manually copied from the Heurist database. Will implement this once the Heurist team fixes 
  this.)
- [x] Read the configuration directly from the RO-Crate archive (`.zip`) file.
- [x] Output complete whole RO-Crate archive (`.zip`) file from the `rocrate:create` command, which includes the
 uploaded files from Heurist as well.
