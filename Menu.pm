#!/usr/bin/perl
package Menu;

use strict;
use warnings;
use Exporter qw(import);
our @EXPORT_OK = qw(show_menu show_message);

# show_menu($cui)
# Creates and displays a side menu with several options.
sub show_menu {
    my ($cui) = @_;
    my $menu = $cui->add(
        'side_menu', 'Menubar',
        -menu => [
            { -label => 'אפשרות 1',    -value => sub { show_message($cui, "נבחרה אפשרות 1"); } },
            { -label => 'אפשרות 2',    -value => sub { show_message($cui, "נבחרה אפשרות 2"); } },
            { -label => 'ניהול בקשות', -value => sub { show_message($cui, "ניהול בקשות משמרת"); } },
            { -label => 'יציאה',       -value => sub { $cui->quit; } },
        ],
        -x  => -1,          # Align to right edge of screen.
        -y  => 1,
        -fg => 'white',
        -bg => 'blue',
    );
    $menu->focus();  # Set focus to the side menu.
}

# show_message($cui, $msg)
# Displays a dialog message with an OK button.
sub show_message {
    my ($cui, $msg) = @_;
    my $dialog = $cui->add(
        'dialog', 'Dialog::Message',
        -message => $msg,
        -buttons => ['אישור'],
    );
    $dialog->focus();
    $dialog->get();
    $cui->delete('dialog');  # Remove dialog after use.
}

1;  # Return true value for the module.