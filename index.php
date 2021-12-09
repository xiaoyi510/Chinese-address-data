<?php
require_once './vendor/autoload.php';


use QL\QueryList;

$data = parseProvince('index');

//打印结果
$list = [];
foreach ($data->all() as $province) {
    if (!empty($province['link'])) {
        //>> 枚举省份ID
        $provinceId = str_replace('.html', '', $province['link']);
        $provinceIdSave = str_pad($provinceId, 6, '0');
        $list['1'][] = [
            'id'   => $provinceIdSave,
            'name' => $province['name']
        ];
        print_r("获取城市:".$province['name'] . PHP_EOL);
        $cityList = parseCity($provinceId);
        foreach ($cityList as $city) {
            $cityId = substr($city['id'], 0, 6);
            if ($city['name'] == '市辖区') {
                $city['name'] = $province['name'];
            }
            $list[$provinceIdSave][] = [
                'id'   => $cityId,
                'name' => $city['name']
            ];
            $nextAreaId = getSubstr($city['link'],'/','.html');


            //>> 获取出区域
            $class= 'countytable';
            print_r("获取[".$city['name']."]区域数据:");
            $areaListItems=  parseCity([$provinceId,$nextAreaId], $class);
            //var_dump($cityListItems->all());die();
            foreach ($areaListItems->all() as $areaItem ){
                $areaId = explode('/', str_replace('.html', '', $areaItem['link']));
                // 市辖区没有连接
                if(count($areaId) != 2){
                    continue;
                }
                $list[$cityId][] = [
                    'id'   => $areaId[1],
                    'name' => $areaItem['name']
                ];

                print_r("获取[".$areaItem['name']."]街道:");

                $class = 'towntable';
                //>> link 最后一个为区号
                $streetListItems = parseCity([$provinceId,$areaId[0],$areaId[1]], $class);
                //>> 增加街道
                foreach ($streetListItems->all() as $streetItem){
                    $nextStreetId = explode('/', str_replace('.html', '', $streetItem['link']));

                    $list[$areaId[1]][] = [
                        'id'   => $nextStreetId[1],
                        'name' => $streetItem['name']
                    ];
                }
            }
        }
    }
}

file_put_contents('./f地区.json', json_encode($list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
print_r('获取完成');
die();

//>> 获取省份下面的城市
function parseProvince($provinceId)
{


    $url = 'http://www.stats.gov.cn/tjsj/tjbz/tjyqhdmhcxhfdm/2020/' . $provinceId . '.html';

    $rules = [  //设置采集规则
                // 采集所有a标签的href属性
                'link' => ['a', 'href'],
                // 采集所有a标签的文本内容
                'name' => ['a', 'text']
    ];

//手动转码
    $html = iconv('GB2312', 'UTF-8', file_get_contents($url));

//然后可以把页面源码或者HTML片段传给QueryList
    $data = QueryList::html($html)
        ->rules($rules)->range('.provincetr td')->query()->getData();
    return $data;
}

//>> 获取省份下面的城市
function parseCity($addressIds,$class='citytable')
{
    if(!is_array($addressIds)){
        $addressIds = [$addressIds];
    }

    $url = 'http://www.stats.gov.cn/tjsj/tjbz/tjyqhdmhcxhfdm/2020/' . implode('/', $addressIds) . '.html';

    $rules = [  //设置采集规则
                // 采集所有a标签的href属性
                'link' => ['td:eq(0) a', 'href'],
                'id'   => ['td:eq(0) a', 'text'],
                // 采集所有a标签的文本内容
                'name' => ['td:eq(1) a', 'text']
    ];

    do{
        print_r(PHP_EOL."访问url[$class]".$url.PHP_EOL);
        $data = curl_get($url);
    }while($data == null);

//手动转码
    $html = mb_convert_encoding($data, 'UTF-8','GB2312');

//然后可以把页面源码或者HTML片段传给QueryList
    $data = QueryList::html($html)
        ->rules($rules)->range('.'.$class.' tr:gt(0)')->query()->getData();
    return $data;
}




/*以下是取中间文本的函数
  getSubstr=调用名称
  $str=预取全文本
  $leftStr=左边文本
  $rightStr=右边文本
*/
function getSubstr($str, $leftStr, $rightStr)
{
    $left = strpos($str, $leftStr);
    //echo '左边:'.$left;
    $right = strpos($str, $rightStr,$left);
    //echo '<br>右边:'.$right;
    if($left < 0 or $right < $left) return '';
    return substr($str, $left + strlen($leftStr), $right-$left-strlen($leftStr));
}


function curl_get($url){

    $header = array(
        'Accept: application/json',
    );
    $curl = curl_init();
    //设置抓取的url
    curl_setopt($curl, CURLOPT_URL, $url);
    //设置头文件的信息作为数据流输出
    curl_setopt($curl, CURLOPT_HEADER, 0);
    // 超时设置,以秒为单位
    curl_setopt($curl, CURLOPT_TIMEOUT, 1);

    // 超时设置，以毫秒为单位
    curl_setopt($curl, CURLOPT_TIMEOUT_MS, 1000);

    // 设置请求头
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    //设置获取的信息以文件流的形式返回，而不是直接输出。
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    //执行命令
    $data = curl_exec($curl);

    // 显示错误信息
    if (curl_error($curl)) {
        print "Error: " . curl_error($curl).PHP_EOL;
        return null;
    } else {
        // 打印返回的内容
        curl_close($curl);
        return $data;
    }
}