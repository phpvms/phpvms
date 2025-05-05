#!/bin/bash
if command -v git &> /dev/null
then
    echo "Adding Git hooks"
    git config core.hooksPath .hooks
else
    echo "Git is not installed. Skipping Git hooks setup."
fi
