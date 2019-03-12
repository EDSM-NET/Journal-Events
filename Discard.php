<?php
/**
 * Elite Dangerous Star Map
 * @link https://www.edsm.net/
 */

namespace   Journal;

class Discard
{
    // Events that we discard
    static public $events = [
        // EDMC / EDD / EDDI
        //'StartUp', // Give first system response to EDMC
        'ShutDown',
        'EDDItemSet',
        'EDDCommodityPrices',
        'ModuleArrived',
        'ShipArrived',
        'Coriolis',
        'EDShipyard',

        // Extra files (Taking them from EDDN)
        'Market',
        'Shipyard',
        'Outfitting',
        'ModuleInfo',
        'Status',

        // Load events
        'Fileheader',
        'Commander',
        'NewCommander',
        'ClearSavedGame',
        'Music',
        'Continued',
        'Passengers',

        // Docking events
        'DockingCancelled',
        'DockingDenied',
        'DockingGranted',
        'DockingRequested',
        'DockingTimeout',

        // Fly events
        'StartJump',
        'Touchdown',
        'Liftoff',
        'NavBeaconScan',
        'SupercruiseEntry',
        'SupercruiseExit',

        // We might reconsider this, and see if we can do something about crime report?
        'UnderAttack',
        'ShipTargeted',
        'Scanned',
        'DataScanned',
        'DatalinkScan',

        // Engineer
        'EngineerApply',
        'EngineerLegacyConvert',

        // Reward (See RedeemVoucher for credits)
        'FactionKillBond',
        'Bounty',
        'CapShipBond',
        'DatalinkVoucher',

        // Ship events
        'SystemsShutdown',
        'EscapeInterdiction',
        'HeatDamage',
        'HeatWarning',
        'HullDamage',
        'ShieldState',
        'FuelScoop',
        'LaunchDrone',
        'AfmuRepairs',
        'CockpitBreached',
        'ReservoirReplenished',

        'ApproachBody',
        'LeaveBody',
        'DiscoveryScan',
        'MaterialDiscovered',
        'Screenshot',

        // NPC Crew
        'CrewAssign',
        'CrewFire',
        'NpcCrewRank',

        // Shipyard / Outfitting
        'ShipyardNew',
        'StoredModules',
        'MassModuleStore',
        'ModuleStore',
        'ModuleSwap',

        // Powerplay
        'PowerplayVote',
        'PowerplayVoucher',

        'ChangeCrewRole',
        'CrewLaunchFighter',
        'CrewMemberJoins',
        'CrewMemberQuits',
        'CrewMemberRoleChange',
        'KickCrewMember',
        'EndCrewSession', // ??

        'LaunchFighter',
        'DockFighter',
        'FighterDestroyed',
        'FighterRebuilt',
        'VehicleSwitch',
        'LaunchSRV',
        'DockSRV',
        'SRVDestroyed',

        'JetConeBoost',
        'JetConeDamage',

        'RebootRepair',
        'RepairDrone',

        // Wings
        'WingAdd',
        'WingInvite',
        'WingJoin',
        'WingLeave',

        // Chat
        'ReceiveText',
        'SendText',

        // End of game
        'Shutdown',
    ];
}