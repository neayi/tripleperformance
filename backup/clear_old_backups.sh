#!/bin/bash

find . -name "*.gz" -type f -mtime +10 -exec rm -f {} \;
