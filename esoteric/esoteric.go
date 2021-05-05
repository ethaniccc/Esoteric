package esoteric

import "github.com/ethaniccc/esoteric/esoteric/config"

// Instance ...
type Instance struct {
	config config.Config
}

// New creates a new Esoteric instance and returns it.
func New(conf config.Config) *Instance {
	return &Instance{config: conf}
}

// NewWithDefaultConfig ...
func NewWithDefaultConfig() *Instance {
	return &Instance{config: config.Default()}
}
