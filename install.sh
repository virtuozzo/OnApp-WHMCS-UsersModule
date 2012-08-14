#!/bin/sh

tmpDir='/tmp/OnAppWHMCSUserModule'

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
			i=false
		else
			echo "Entered value [ ${OnAppVersion} ] does not seem to be a valid version number"
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
			echo "Entered value [ ${WHMCSDir} ] does not seem to be a valid WHMCS directory"
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
cd "${modulesDir}"
zip -rq9m "./onappusers-`date "+%Y-%m-%d %H-%M-%S"`" "onappusers"  > /dev/null
cd "${WHMCSDir}/includes"
zip -rq9m "./wrapper-`date "+%Y-%m-%d %H-%M-%S"`" "wrapper" > /dev/null
cd "${WHMCSDir}/includes/hooks"
zip -rq9m "./onappusers-`date "+%Y-%m-%d %H-%M-%S"`" "onappusers.php" > /dev/null

# copy new files
cp -r ${tmpDir}/OnApp-WHMCS-UsersModule/* ${WHMCSDir}

# delete tmp folder
rm -rf ${tmpDir}

echo
echo 'Installation finished'