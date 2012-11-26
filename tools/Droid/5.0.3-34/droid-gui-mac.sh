#!/bin/sh

# DROID GUI launch script for Mac OS X systems

OPTIONS=-Dcom.apple.macos.useScreenMenuBar=true
OPTIONS=$OPTIONS" -Xdock:name=DROID"
OPTIONS=$OPTIONS" -Dcom.apple.mrj.application.growbox.intrudes=false"
OPTIONS=$OPTIONS" -Dcom.apple.mrj.application.live-resize=true"

java $OPTIONS -jar droid-ui-5.0.3.jar

