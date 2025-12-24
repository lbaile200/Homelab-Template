Included in this folder are all the packages, flatpaks, flatpak remotes, and repos that were user(me) installed.  

They were extracted with:

dnf repoquery --userinstalled --qf "%{name}" > dnf-packages.txt
flatpak list --app --columns=application > flatpak-apps.txt
flatpak remotes --columns=name,url > flatpak-remotes.txt
dnf repolist --enabled -q | awk 'NR>1 {print $1}' > enabled-repos.txt


They can be reinstalled with:
sudo dnf install $(cat dnf-packages.txt)
flatpak install $(cat flatpak-apps.txt)

For recreation of repos and previously installed plugins, see script in root directory
reporestore.sh


