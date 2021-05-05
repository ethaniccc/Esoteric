package detection

// Detection ...
type Detection struct {
	// Enabled ...
	Enabled bool `yml:"enabled"`
	// PunishmentType ...
	PunishmentType string `yml:"punishment_type"`
	// Maximum ...
	Maximum int16 `yml:"max_v1"`
	// MaximumRaw ...
	MaximumRaw float64 `yml:"max_raw,omitempty"`
	// MaximumDistance ...
	MaximumDistance float64 `yml:"max_dist,omitempty"`
	// Code ...
	Code string `yml:"code"`
	// MaximumCPS ...
	MaximumCPS int16 `yml:"max_cps,omitempty"`
	// Samples ...
	Samples int16 `yml:"samples,omitempty"`
	// MaximumDuplicates ...
	MaximumDuplicates int16 `yml:"max_duplicates,omitempty"`
	// DifferenceMax ...
	DifferenceMax float64 `yml:"diff_max,omitempty"`
	// Setback ...
	Setback bool `yml:"setback,omitempty"`
	// Percent ...
	Percent float64 `yml:"pct,omitempty"`
}

// All returns all default detections.
func All() map[string]map[string]Detection {
	return map[string]map[string]Detection{
		"Autoclicker": {
			"A": {
				Enabled:        true,
				PunishmentType: "none",
				Maximum:        5,
				Code:           "Noah",
				MaximumCPS:     21,
			},
			"B": {
				Enabled:           true,
				PunishmentType:    "none",
				Maximum:           3,
				Code:              "Crocodile",
				Samples:           10,
				MaximumDuplicates: 4,
			},
		},
		"Aim": {
			"A": {
				Enabled:        true,
				PunishmentType: "none",
				Maximum:        5,
				Code:           "Neck",
			},
			"B": {
				Enabled:        true,
				PunishmentType: "none",
				Maximum:        5,
				Code:           "Hawkeye",
			},
		},
		"Killaura": {
			"A": {
				Enabled:        true,
				PunishmentType: "none",
				Maximum:        5,
				Code:           "Lancelot",
			},
			"B": {
				Enabled:        true,
				PunishmentType: "none",
				Maximum:        10,
				Code:           "Inkingmistake",
			},
		},
		"Range": {
			"A": {
				Enabled:         true,
				PunishmentType:  "none",
				Maximum:         20,
				MaximumRaw:      3.05,
				MaximumDistance: 3.01,
				Code:            "Aristotle",
			},
		},
		"Fly": {
			"A": {
				Enabled:        true,
				PunishmentType: "none",
				Maximum:        20,
				Code:           "Albatross",
				DifferenceMax:  0.015,
				Setback:        true,
			},
			"B": {
				Enabled:        true,
				PunishmentType: "none",
				Maximum:        20,
				Code:           "Hummingbird",
				DifferenceMax:  0.01,
				Setback:        true,
			},
			"C": {
				Enabled:        true,
				PunishmentType: "none",
				Maximum:        2,
				Code:           "Blackhawk",
				Setback:        true,
			},
		},
		"Motion": {
			"A": {
				Enabled:        true,
				PunishmentType: "none",
				Maximum:        10,
				Code:           "Mach1",
				Setback:        true,
			},
			"B": {
				Enabled:        true,
				PunishmentType: "none",
				Maximum:        10,
				Code:           "Mach2",
				Setback:        true,
			},
			"C": {
				Enabled:        true,
				PunishmentType: "none",
				Maximum:        10,
				Code:           "Mach3",
				Setback:        true,
			},
		},
		"Velocity": {
			"A": {
				Enabled:        true,
				PunishmentType: "none",
				Maximum:        20,
				Code:           "Hypersonic",
				Percent:        99.99,
			},
			"B": {
				Enabled:        false,
				PunishmentType: "none",
				Maximum:        20,
				Code:           "Lightspeed",
			},
		},
		"EditionFaker": {
			"A": {
				Enabled:        true,
				PunishmentType: "kick",
				Maximum:        1,
				Code:           "Janus",
			},
		},
		"Packets": {
			"A": {
				Enabled:        true,
				PunishmentType: "kick",
				Maximum:        1,
				Code:           "BP1",
			},
			"B": {
				Enabled:        true,
				PunishmentType: "kick",
				Maximum:        10,
				Code:           "MPP",
			},
			"C": {
				Enabled:        true,
				PunishmentType: "kick",
				Maximum:        2,
				Code:           "JPPM",
			},
		},
	}
}
