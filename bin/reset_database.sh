#!/bin/bash
cd ..
cd database_structure
echo "drop database opf_ref" | mysql -u root
echo "create database opf_ref" | mysql -u root
mysql -u root opf_ref < opf_ref.sql
