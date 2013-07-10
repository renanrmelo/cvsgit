#!bash
#
# bash completion support for symfony2 console
#
# Copyright (C) 2011 Matthieu Bontemps <matthieu@knplabs.com>
# Distributed under the GNU General Public License, version 2.0.
#
# INSTALACAO:
# - colocar em /etc/bash_completion.d/ 
# - necessario permisao de execucao: cmod +x

_console()
{
    local cur prev opts cmd
    COMPREPLY=()
    cur="${COMP_WORDS[COMP_CWORD]}"
    prev="${COMP_WORDS[COMP_CWORD-1]}"
    cmd="${COMP_WORDS[0]}"
    
    #
    # caso for informado 2 comandos, ignora autocomplete
    #
    if [[ "${COMP_CWORD}" == 2 ]]; then
      return 0;
    fi

    PHP='$ret = shell_exec($argv[1]);

$ret = preg_replace("/^.*Available commands:\n/s", "", $ret);
$ret = explode("\n", $ret);

$comps = array();
foreach ($ret as $line) {
    if (preg_match("@^  ([^ ]+) @", $line, $m)) {
        $comps[] = $m[1];
    }
}

echo implode("\n", $comps);
'
    possible=$($(which php) -r "$PHP" $COMP_WORDS);
    COMPREPLY=( $(compgen -W "${possible}" -- ${cur}) )
    return 0
}

complete -F _console -o default console
#complete -F _console console
COMP_WORDBREAKS=${COMP_WORDBREAKS//:}
