RED='\033[0;31m'
NC='\033[0m'

function setUp () {
  tempDir=$(mktemp -d)
  tempDir=$(mktemp -d)
  pushd "$tempDir" >/dev/null || exit
}

function tearDown () {
  popd >/dev/null || exit
  rm -rf "$tempDir"
}

function die () {
  printf "${RED}%s${NC}" "$@" 1>&2
  exit 1
}

function assertOk () {
  if ! output=$("$@"); then die "Failed assertion of $*
$output"; fi
}

function assertNotOk () {
  if output=$("$@"); then die "Failed negative assertion of $*
$output"; fi
}

function assertEqual () {
    if [ -z "$2" ]; then
        echo "Missing Arguments: assertEqual arg1 arg2"
        return 99
    fi
    assertOk [ "$1" == "$2" ]
}
