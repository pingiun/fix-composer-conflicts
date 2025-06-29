<?php

namespace Pingiun\FixConflicts;

enum ResolutionChoice
{
    case OURS;
    case BASE;
    case THEIRS;
    case NEWEST;
    case REMOVE;
    case MANUAL;
    case ABORT;
}
