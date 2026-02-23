<?php

namespace Malikad778\MigrationGuard\Issues;

enum IssueSeverity: string
{
    case BREAKING = 'breaking';
    case HIGH     = 'high';
    case MEDIUM   = 'medium';
}
