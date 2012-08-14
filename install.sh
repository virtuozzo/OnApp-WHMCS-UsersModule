#!/bin/sh
# Installator for OnApp WHMCS Users Module
# author	Lev Bartashevsky
# copyright	(c) 2012 OnApp

tmpDir='/tmp/OnAppWHMCSUserModule'
installator=`pwd`/`basename $0`

echo
i=true
while [ ${i} == true ]
do
	echo "Specify your OnApp version [ X.X.X ]: \c"
	read OnAppVersion
	# check version format
	regex="^[2-3]\.[0-5]\.[0-5]$"
	if [[ "${OnAppVersion}" =~ $regex ]]
		then
			if git ls-remote --heads https://github.com/OnApp/OnApp-WHMCS-UsersModule.git | grep refs/heads/${OnAppVersion} &> /dev/null
				then
					i=false
				else
					echo
					echo 'ERROR: There is no code for specified OnApp version.'
					echo 'Please, contact support.'
					exit 1
			fi
		else
			echo "Entered value [ ${OnAppVersion} ] seems to be not valid version number"
	fi
done

i=true
while [ ${i} == true ]
do
	echo "Specify your WHMCS directory: \c"
	read WHMCSDir
	# check if dir exists
	modulesDir="${WHMCSDir}/modules/servers"
	if [ -d ${modulesDir} ]
		then
			i=false
		else
			echo "Entered value [ ${WHMCSDir} ] seems to be not valid WHMCS directory"
	fi
done

echo
mkdir ${tmpDir}
cd ${tmpDir}

# clone module
git clone https://github.com/OnApp/OnApp-WHMCS-UsersModule.git -b ${OnAppVersion}
# clone wrapper
git clone https://github.com/OnApp/OnApp-PHP-Wrapper-External.git -b ${OnAppVersion}

# delete unnecessary stuff
find . -name '.git*' | xargs rm -rf
find ./OnApp-WHMCS-UsersModule -type f -maxdepth 1 | xargs rm -rf

# copy wrapper into module
mv ./OnApp-PHP-Wrapper-External/* ./OnApp-WHMCS-UsersModule/includes/wrapper

# backup previous module/wrapper/hooks versions
date=`date "+%Y-%m-%d %H-%M-%S"`
if command -v zip &> /dev/null
    then
		cd "${modulesDir}"
		zip -rq9m "./onappusers-${date}" "onappusers"  > /dev/null
		cd "${WHMCSDir}/includes"
		zip -rq9m "./wrapper-${date}" "wrapper" > /dev/null
		cd "${WHMCSDir}/includes/hooks"
		zip -rq9m "./onappusers.php-${date}" "onappusers.php" > /dev/null
    else
		echo
		echo 'zip command was not found, so just rename files'
		mv "${modulesDir}/onappusers" "${modulesDir}/onappusers-${date}"
		mv "${WHMCSDir}/includes/wrapper" "${WHMCSDir}/includes/wrapper-${date}"
		mv "${WHMCSDir}/includes/hooks/onappusers.php" "${WHMCSDir}/includes/hooks/onappusers.php-${date}"
fi

# copy new files
cp -r ${tmpDir}/OnApp-WHMCS-UsersModule/* ${WHMCSDir}

# delete tmp stuff
rm -rf ${tmpDir}
rm ${installator}

echo
echo 'Installation finished.'