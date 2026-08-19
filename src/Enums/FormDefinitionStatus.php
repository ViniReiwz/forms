<?php

namespace Uspdev\Forms\Enums;

enum FormDefinitionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Disabled = 'disabled';
}
