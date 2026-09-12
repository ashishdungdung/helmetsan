#!/bin/bash
echo "--- GIT STATUS ---" > git_debug.log
git status >> git_debug.log 2>&1
echo "--- GIT REMOTE ---" >> git_debug.log
git remote -v >> git_debug.log 2>&1
echo "--- GIT PUSH ---" >> git_debug.log
git push -u origin main >> git_debug.log 2>&1
