#!/bin/sh

mkdir var
cd var
mkdir logs
mkdir cache

cd cache
mkdir annotations
mkdir view
mkdir metadata
mkdir data

cd ../../
chmod -R 0777 var