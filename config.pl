#!/usr/bin/perl
use strict;
use warnings;
use Curses::UI;

# Initialize the Curses::UI environment.
# This file sets up and exports the $cui variable for use in other parts of the application.
our $cui = Curses::UI->new(
    -clear_on_exit => 1,
);

1;  # Return true value for the module.