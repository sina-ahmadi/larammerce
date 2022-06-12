#!/usr/bin/env bash

echo "Taraznegar Driver : started fetching products data ...";

TARAZNEGAR_DATA_PATH=${DATA_PATH}/taraznegar;
TARAZNEGAR_PRODUCTS_NEW=${TARAZNEGAR_DATA_PATH}/products_new.txt
TARAZNEGAR_PRODUCTS_OLD=${TARAZNEGAR_DATA_PATH}/products_old.txt
TARAZNEGAR_PRODUCTS_DIF=${TARAZNEGAR_DATA_PATH}/products_dif.txt
TARAZNEGAR_PRODUCTS_ERR=${TARAZNEGAR_DATA_PATH}/products_err.txt

if [ ! -d ${TARAZNEGAR_DATA_PATH} ]; then
    mkdir -p ${TARAZNEGAR_DATA_PATH}
fi;

echo -e "\n\nThe process ended with these errors :\n-------------------------------------" > ${TARAZNEGAR_PRODUCTS_ERR};

ARTISAN_UPDATE_PRODUCT="${ECOMMERCE_BASE_PATH}/artisan products:update-stock"
ARTISAN_EXPORT_PRODUCT="${ECOMMERCE_BASE_PATH}/artisan products:export --type=json --cols=code,count,latest_price"

if [ ! -d ${TARAZNEGAR_DATA_PATH} ];then
    mkdir -p ${TARAZNEGAR_DATA_PATH};
fi;

#grab pid of this process and update the pid file with it
PID=`ps -ef | grep ${FIN_MAN_DRIVER} | head -n1 |  awk ' {print $2;} '`;
echo "${PID}" > ${PID_FILE};

TARAZNEGAR_HOST=`${SCRIPTS_PATH}/_get_env.sh FIN_MAN_HOST`;
TARAZNEGAR_PREFIX=`${SCRIPTS_PATH}/_get_env.sh FIN_MAN_PREFIX`
TARAZNEGAR_HOST="${TARAZNEGAR_HOST}/${TARAZNEGAR_PREFIX}/api"
TARAZNEGAR_PORT=`${SCRIPTS_PATH}/_get_env.sh FIN_MAN_PORT`
TARAZNEGAR_TOKEN=`${SCRIPTS_PATH}/_get_env.sh FIN_MAN_TOKEN`;

${ARTISAN_EXPORT_PRODUCT} \
    | jq -r '.[] | "{\"code\":\"\(.code)\", \"count\":\"\(.count)\", \"price\":\"\(.fin_man_price)\"}"' \
    | sort > ${TARAZNEGAR_PRODUCTS_OLD};


timeout 60s curl -H "content-type: application/json" -s \
    http://${TARAZNEGAR_HOST}/products \
    | jq -r '.[] | "{\"code\":\"\(.relation)\", \"count\":\"\(.quantity)\", \"price\":\"\(.price)\"}"' \
    | sort > ${TARAZNEGAR_PRODUCTS_NEW};

if [ -s ${TARAZNEGAR_PRODUCTS_NEW} ];then
    if [ ! -f ${TARAZNEGAR_PRODUCTS_OLD} ];then
        touch ${TARAZNEGAR_PRODUCTS_OLD}
    fi;

    comm -23 ${TARAZNEGAR_PRODUCTS_OLD} ${TARAZNEGAR_PRODUCTS_NEW} | jq -r '.code' \
        | uniq > ${TARAZNEGAR_PRODUCTS_DIF};

    while read P_CODE; do
        ${ARTISAN_UPDATE_PRODUCT} --code="${P_CODE}"
        RES_CODE=$?;
        if [ ${RES_CODE} -eq '1' ];then
            echo -e "code: ${P_CODE}\t failed : there are no product with this code." >> ${TARAZNEGAR_PRODUCTS_ERR};
        elif [ ${RES_CODE} -eq '2' ];then
            echo -e "code: ${P_CODE}\t failed : there are more than one product with this code." >> ${TARAZNEGAR_PRODUCTS_ERR};
        elif [ ${RES_CODE} -eq '3' ];then
            echo -e "code: ${P_CODE}\t failed : there were error in fetching data from fin server or saving it on local database." >> ${TARAZNEGAR_PRODUCTS_ERR};
        fi;

    done <${TARAZNEGAR_PRODUCTS_DIF}

    cat ${TARAZNEGAR_PRODUCTS_ERR};
fi;

if [ -f ${PID_FILE} ]; then
    rm -f ${PID_FILE}
fi

