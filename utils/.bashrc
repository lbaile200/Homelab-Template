# .bashrc

# Source global definitions
if [ -f /etc/bashrc ]; then
    . /etc/bashrc
fi

# User specific environment
if ! [[ "$PATH" =~ "$HOME/.local/bin:$HOME/bin:" ]]; then
    PATH="$HOME/.local/bin:$HOME/bin:$PATH"
fi
export PATH

# Uncomment the following line if you don't like systemctl's auto-paging feature:
# export SYSTEMD_PAGER=

# User specific aliases and functions
if [ -d ~/.bashrc.d ]; then
    for rc in ~/.bashrc.d/*; do
        if [ -f "$rc" ]; then
            . "$rc"
        fi
    done
fi
unset rc
alias sound='systemctl --user restart wireplumber pipewire pipewire-pulse'
alias please='sudo $(history -p !!)'
alias bluetooth='sudo systemctl restart bluetooth'
alias plasma-restart='systemctl restart --user plasma-plasmashell'
alias fetch='fastfetch'
alias rpm-dated="rpm -qa --qf '(%{INSTALLTIME:date}): %{NAME}-%{VERSION}\n'"
alias bash-reload=". ~/.bashrc"
alias terminal-transparency="dconf write /org/gnome/Ptyxis/Profiles/b9cb2f0dd5ff703a1b036a4f6778dc77/opacity 0.95"
alias lsgit="find ~ -type d -name ".git" -prune 2>/dev/null | sed 's|/.git$||' | sort"
export MANPAGER="nano -"
