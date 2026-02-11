#!/bin/sh
rm -rf ./public/build/*
cp -R ./build/* ./public/build
rm -rf ./build
