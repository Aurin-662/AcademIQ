<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('academiq:expire-requests')->hourly();
