<?php

namespace App\Enums;

enum FeatureTourSegment: string
{
    case Feed = 'feed';
    case Circles = 'circles';
    case CircleDetail = 'circle-detail';
    case Persons = 'persons';
    case DefaultCircles = 'default-circles';
    case Give = 'give';
    case Map = 'map';
    case Profile = 'profile';
}
