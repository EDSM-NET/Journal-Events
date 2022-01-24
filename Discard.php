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

        // Squadron
        'SquadronCreated',
        'SquadronStartup',
        'DisbandedSquadron',

        'InvitedToSquadron',
        'AppliedToSquadron',
        'JoinedSquadron',
        'LeftSquadron',

        'SharedBookmarkToSquadron',

        // Fleet Carrier
        'CarrierStats',
        'CarrierTradeOrder',
        'CarrierFinance',
        'CarrierBankTransfer',
        'CarrierCrewServices',

        'CarrierJumpRequest',
        'CarrierJumpCancelled',
        'CarrierDepositFuel',
        'CarrierDockingPermission',
        'CarrierModulePack',

        'CarrierBuy',
        'CarrierNameChange',
        'CarrierDecommission',

        // Odyssey
        'BookDropship',
        'CancelDropship',
        'DropshipDeploy',

        // Odyssey - Backed by BackpackChange
        'CollectItems',
        'DropItems',

        'Disembark', //TODO: Use for Foot discovery
        'Embark',

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
        'NavRoute',

        // We might reconsider this, and see if we can do something about crime report?
        'PVPKill',
        'CrimeVictim',
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
        'CargoTransfer', //TODO: Synched in Cargo?!

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
        
        // Suit
        'SuitLoadout',
        'SwitchSuitLoadout',
        'CreateSuitLoadout',
        'LoadoutEquipModule',

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

        // Temp Discard...
        'FSSSignalDiscovered',
        'AsteroidCracked',
        'ProspectedAsteroid',
        'ScanBaryCentre',
        'FSSBodySignals',
        'SAASignalsFound',
        'ScanOrganic',
    ];
}