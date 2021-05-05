package config

import "github.com/ethaniccc/esoteric/esoteric/config/detection"

// WebhookSettings ...
type WebhookSettings struct {
	// Link ...
	Link string `yml:"link"`
	// Alerts ...
	Alerts bool `yml:"alerts"`
	// Punishments ...
	Punishments bool `yml:"punishments"`
}

// WaveSettings ...
type WaveSettings struct {
	// Enabled ...
	Enabled bool `yml:"enabled"`
	// Violations
	Violations int16 `yml:"violations"`
	// BanLength ...
	BanLength string `yml:"ban_length,omitempty"`
	// StartMessage ...
	StartMessage string `yml:"start_message,omitempty"`
	// BanMessage ...
	BanMessage string `yml:"ban_message,omitempty"`
	// EndMessage ...
	EndMessage string `yml:"end_message,omitempty"`
}

// TimeoutSettings ...
type TimeoutSettings struct {
	// Enabled ...
	Enabled bool `yml:"enabled"`
	// TotalPackets ...
	TotalPackets int32 `yml:"total_packets"`
	// Ticks ...
	Ticks int32 `yml:"ticks"`
}

// Config is a configuration file for Esoteric used to change some elements of the proxy.
type Config struct {
	// Prefix ...
	Prefix string `yml:"prefix"`
	// AlertCoolDown ...
	AlertCoolDown float64 `yml:"alert_cooldown"`
	// AlertMessage ...
	AlertMessage string `yml:"alert_message"`
	// BanLength ...
	BanLength string `yml:"ban_length"`
	// SetbackType ...
	SetbackType string `yml:"setback_type"`
	// KickMessage ...
	KickMessage string `yml:"kick_message"`
	// BanMessage ...
	BanMessage string `yml:"ban_message"`
	// WebhookSettings ...
	WebhookSettings WebhookSettings `yml:"webhook"`
	// WaveSettings ...
	WaveSettings WaveSettings `yml:"banwaves"`
	// TimeoutSettings ...
	TimeoutSettings TimeoutSettings `yml:"timeout"`
	// Detections ...
	Detections map[string]map[string]detection.Detection `yml:"detections"`
}

// Default initializes default config settings in the configuration instance.
func Default() Config {
	return Config{
		Prefix:        "§l§6Eso§fteric§7>§r",
		AlertCoolDown: 4,
		BanLength:     "7",
		AlertMessage:  "%v §e%v §7failed §e%v (%v) §7(§cx%v§7) §7[%v§7]",
		KickMessage:   "%v Kicked (code=%v)\nContact staff with a screenshot of this message if this issue persists",
		BanMessage:    "%v Banned (code=%v)\nMake a ticket with a screenshot of this message if this is a mistake\nExpires: %v",
		SetbackType:   "none",
		TimeoutSettings: TimeoutSettings{
			Enabled:      true,
			TotalPackets: 20,
			Ticks:        20,
		},
		WaveSettings: WaveSettings{
			Enabled:      false,
			Violations:   40,
			BanLength:    "7",
			StartMessage: "§eThe ban wave has §6commenced",
			BanMessage:   "§g%v §ehas §6been §ebanned in the ban wave §7[Wave %v]",
			EndMessage:   "§eThe ban wave has §6concluded§e - remember to play legit!",
		},
		WebhookSettings: WebhookSettings{
			Link:        "none",
			Alerts:      false,
			Punishments: false,
		},
		Detections: detection.All(),
	}
}
