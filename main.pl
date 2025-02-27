#!/usr/bin/perl
use strict;
use warnings;
use Curses::UI;

###############################################################################
# Module Information:
#   Name:         Curses::UI
#   Version:      0.9609
#   Location:     /usr/share/perl5/Curses/UI.pm
#   Documentation: https://metacpan.org/pod/Curses::UI
#
# This module is not part of the Perl core, and you can search for it on CPAN.
###############################################################################

# Initialize the Curses::UI environment.
my $cui = Curses::UI->new(
    -clear_on_exit => 1,
);

# Create the main window.
my $win = $cui->add(
    'main_win', 'Window',
    -border => 1,
    -title  => 'תפריט ראשי',
);

# Create a button with a hamburger menu icon.
# When pressed, it will display a side menu on the right.
my $menu_button = $win->add(
    'menu_button', 'Buttonbox',
    -buttons => [
        { -label => '≡', -value => \&show_menu },
    ],
    -x     => -3,   # Position near the right edge of the window.
    -y     => 0,
    -width => 5,
);

# This function displays a side menu with the relevant actions.
sub show_menu {
    my $menu = $cui->add(
        'side_menu', 'Menubar',
        -menu => [
            { -label => 'אפשרות 1',    -value => sub { show_message("נבחרה אפשרות 1"); } },
            { -label => 'אפשרות 2',    -value => sub { show_message("נבחרה אפשרות 2"); } },
            { -label => 'ניהול בקשות', -value => sub { show_message("ניהול בקשות משמרת"); } },
            { -label => 'יציאה',       -value => sub { $cui->quit; } },
        ],
        -x  => -1,  # Align menu to the right side of the screen.
        -y  => 1,
        -fg => 'white',
        -bg => 'blue',
    );
    $menu->focus();  # Set focus to the side menu so the user can navigate the options.
}

# This function displays a simple dialog message.
sub show_message {
    my $msg = shift;
    my $dialog = $cui->add(
        'dialog', 'Dialog::Message',
        -message => $msg,
        -buttons => ['אישור'],
    );
    $dialog->focus();
    # Wait for the user to press the button.
    $dialog->get();
    $cui->delete('dialog');  # Delete the dialog after the user acknowledges it.
}

$cui->mainloop;