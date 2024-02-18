<?php

namespace Gazelle\Util;

enum IrcText: string {
    case Bold        = "\x02";
    case ColorOff    = "\x03";
    case Reset       = "\x0f";

    case Reverse     = "\x16";
    case Italic      = "\x1d";
    case Strike      = "\x1e";
    case Underline   = "\x1f";

    case White       = "\x0300";
    case Black       = "\x0301";
    case Blue        = "\x0302";
    case Green       = "\x0303";
    case Red         = "\x0304";
    case Brown       = "\x0305";
    case Magenta     = "\x0306";
    case Orange      = "\x0307";
    case Yellow      = "\x0308";
    case LightGreen  = "\x0309";
    case Cyan        = "\x0310";
    case LightCyan   = "\x0311";
    case LightBlue   = "\x0312";
    case Pink        = "\x0313";
    case Grey        = "\x0314";
    case LightGrey   = "\x0315";

    case DarkChocolate      = "\x0316";
    case BrownPod           = "\x0317";
    case VerdunGreen        = "\x0318";
    case TurtleGreen        = "\x0319";
    case Myrtle             = "\x0320";
    case BritishRacingGreen = "\x0321";
    case SherpaBlue         = "\x0322";
    case Tangaroa           = "\x0323";
    case MidnightBlue       = "\x0324";
    case Blackcurrant       = "\x0325";
    case MardiGras          = "\x0326";
    case Blackberry         = "\x0327";

    case Maroon             = "\x0328";
    case RawUmber           = "\x0329";
    case Olive              = "\x0330";
    case FijiGreen          = "\x0331";
    case Green32            = "\x0332";
    case Watercourse        = "\x0333";
    case SurfieGreen        = "\x0334";
    case DarkCerulean       = "\x0335";
    case Navy               = "\x0336";
    case Indigo             = "\x0337";
    case Purple             = "\x0338";
    case TyrianPurple       = "\x0339";

    case FreeSpeechRed      = "\x0340";
    case Tenne              = "\x0341";
    case LaRioja            = "\x0342";
    case Christi            = "\x0343";
    case IslamicGreen       = "\x0344";
    case Jade               = "\x0345";
    case IrisBlue           = "\x0346";
    case NavyBlue           = "\x0347";
    case MediumBlue         = "\x0348";
    case DarkViolet         = "\x0349";
    case DeepMagenta        = "\x0350";
    case JazzberryJam       = "\x0351";

    case Red32              = "\x0352";
    case DarkOrange         = "\x0353";
    case Yellow32           = "\x0354";
    case SpringBud          = "\x0355";
    case Lime               = "\x0356";
    case MediumSpringGreen  = "\x0357";
    case Aqua               = "\x0358";
    case DodgerBlue         = "\x0359";
    case Blue32             = "\x0360";
    case ElectricPurple     = "\x0361";
    case Magenta32          = "\x0362";
    case HollywoodCerise    = "\x0363";

    case Tomato             = "\x0364";
    case TexasRose          = "\x0365";
    case LaserLemon         = "\x0366";
    case Mindaro            = "\x0367";
    case ScreamingGreen     = "\x0368";
    case Aquamarine         = "\x0369";
    case BabyBlue           = "\x0370";
    case MayaBlue           = "\x0371";
    case NeonBlue           = "\x0372";
    case Heliotrope         = "\x0373";
    case PinkFlamingo       = "\x0374";
    case HotPink            = "\x0375";

    case RoseBud            = "\x0376";
    case Caramel            = "\x0377";
    case Canary             = "\x0378";
    case Reef               = "\x0379";
    case MintGreen          = "\x0380";
    case MagicMint          = "\x0381";
    case PaleTurquoise      = "\x0382";
    case ColumbiaBlue       = "\x0383";
    case Perano             = "\x0384";
    case Mauve              = "\x0385";
    case Violet             = "\x0386";
    case LavenderRose       = "\x0387";
}
