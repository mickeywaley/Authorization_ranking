<?php
// 打造邹区最强售前天团计划日度转化率与月度转化率突破c
// 配置信息
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');
define('DATA_FILE', 'conversion_data.txt');
define('SESSION_KEY', 'conversion_admin');

// 店铺名称选项
$shopNames = ['天猫尚显', '天猫名珠', '淘宝尚显', '拼多多尚显', '拼多多尚宇', '拼多多顾家'];

// 班次选项
$shifts = ['白班', '中班', '晚班'];

// 初始化数据文件
if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, json_encode([]));
}

// 验证登录
function isLoggedIn() {
    return isset($_SESSION[SESSION_KEY]) && $_SESSION[SESSION_KEY] === true;
}

// 获取所有数据
function getAllData() {
    $data = json_decode(file_get_contents(DATA_FILE), true);
    return $data ?: [];
}

// 保存数据
function saveData($data) {
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

// 获取唯一姓名列表
function getUniqueNames($data) {
    $names = [];
    foreach ($data as $item) {
        $names[$item['name']] = $item['name'];
    }
    return array_values($names);
}

// 按日期获取数据
function getDataByDate($data, $date) {
    $result = [];
    foreach ($data as $item) {
        if ($item['date'] === $date) {
            $result[] = $item;
        }
    }
    return $result;
}

// 会话初始化
session_start();

// 处理登录
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        $_SESSION[SESSION_KEY] = true;
        header('Location: index.php');
        exit;
    } else {
        $loginError = '用户名或密码错误';
    }
}

// 处理登出
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION[SESSION_KEY]);
    header('Location: index.php');
    exit;
}

// 处理添加数据
if (isset($_POST['add']) && isLoggedIn()) {
    $newData = [
        'id' => uniqid(),
        'date' => $_POST['date'],
        'name' => $_POST['name'],
        'shop' => $_POST['shop'],
        'shift' => $_POST['shift'],
        'conversion_rate' => floatval($_POST['conversion_rate']),
        'breakthrough_count' => intval($_POST['breakthrough_count'])
    ];
    
    $allData = getAllData();
    $allData[] = $newData;
    saveData($allData);
    
    header('Location: index.php');
    exit;
}

// 处理编辑数据
if (isset($_POST['edit']) && isLoggedIn()) {
    $id = $_POST['id'];
    $allData = getAllData();
    
    foreach ($allData as &$item) {
        if ($item['id'] === $id) {
            $item['date'] = $_POST['date'];
            $item['name'] = $_POST['name'];
            $item['shop'] = $_POST['shop'];
            $item['shift'] = $_POST['shift'];
            $item['conversion_rate'] = floatval($_POST['conversion_rate']);
            $item['breakthrough_count'] = intval($_POST['breakthrough_count']);
            break;
        }
    }
    
    saveData($allData);
    header('Location: index.php');
    exit;
}

// 处理删除数据
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isLoggedIn()) {
    $id = $_GET['id'];
    $allData = getAllData();
    
    $newData = [];
    foreach ($allData as $item) {
        if ($item['id'] !== $id) {
            $newData[] = $item;
        }
    }
    
    saveData($newData);
    header('Location: index.php');
    exit;
}

// 获取当前日期
$currentDate = date('Y-m-d');

// 获取所有数据
$allData = getAllData();

// 获取唯一姓名列表
$uniqueNames = getUniqueNames($allData);

// 首页显示最近20条记录
$recentData = array_slice(array_reverse($allData), 0, 20);

// 统计数据 - 个人突破次数排行榜
$individualRankings = [];
foreach ($allData as $item) {
    $key = $item['shop'] . ' - ' . $item['name'];
    if (!isset($individualRankings[$key])) {
        $individualRankings[$key] = [
            'shop' => $item['shop'],
            'name' => $item['name'],
            'daily' => ['count' => 0, 'conversion_rate' => 0],
            'weekly' => ['count' => 0, 'conversion_rate' => 0],
            'monthly' => ['count' => 0, 'conversion_rate' => 0],
            'quarterly' => ['count' => 0, 'conversion_rate' => 0],
            'yearly' => ['count' => 0, 'conversion_rate' => 0],
            'daily_items' => [],
            'weekly_items' => [],
            'monthly_items' => [],
            'quarterly_items' => [],
            'yearly_items' => []
        ];
    }
    
    // 计算时间范围
    $itemDate = new DateTime($item['date']);
    $today = new DateTime();
    $diff = $today->diff($itemDate);
    
    // 单日
    if ($diff->days === 0) {
        $individualRankings[$key]['daily']['count'] += $item['breakthrough_count'];
        $individualRankings[$key]['daily_items'][] = $item;
    }
    
    // 单周
    if ($diff->days <= 7) {
        $individualRankings[$key]['weekly']['count'] += $item['breakthrough_count'];
        $individualRankings[$key]['weekly_items'][] = $item;
    }
    
    // 单月
    if ($diff->m === 0 && $diff->y === 0) {
        $individualRankings[$key]['monthly']['count'] += $item['breakthrough_count'];
        $individualRankings[$key]['monthly_items'][] = $item;
    }
    
    // 季度
    if ($diff->m <= 3 && $diff->y === 0) {
        $individualRankings[$key]['quarterly']['count'] += $item['breakthrough_count'];
        $individualRankings[$key]['quarterly_items'][] = $item;
    }
    
    // 单年
    if ($diff->y === 0) {
        $individualRankings[$key]['yearly']['count'] += $item['breakthrough_count'];
        $individualRankings[$key]['yearly_items'][] = $item;
    }
}

// 计算平均转化率
foreach ($individualRankings as &$ranking) {
    foreach (['daily', 'weekly', 'monthly', 'quarterly', 'yearly'] as $period) {
        $items = $ranking[$period . '_items'];
        if (!empty($items)) {
            $totalRate = 0;
            foreach ($items as $item) {
                $totalRate += $item['conversion_rate'];
            }
            $ranking[$period]['conversion_rate'] = $totalRate / count($items);
        }
    }
    unset($ranking['daily_items']);
    unset($ranking['weekly_items']);
    unset($ranking['monthly_items']);
    unset($ranking['quarterly_items']);
    unset($ranking['yearly_items']);
}

// 排序函数
function sortRankings(&$rankings, $period) {
    usort($rankings, function($a, $b) use ($period) {
        return $b[$period]['count'] - $a[$period]['count'];
    });
}

// 复制数组进行排序
$dailyRankings = $weeklyRankings = $monthlyRankings = $quarterlyRankings = $yearlyRankings = $individualRankings;

// 按不同时间段排序
sortRankings($dailyRankings, 'daily');
sortRankings($weeklyRankings, 'weekly');
sortRankings($monthlyRankings, 'monthly');
sortRankings($quarterlyRankings, 'quarterly');
sortRankings($yearlyRankings, 'yearly');

// 班次突破次数统计
$shiftRankings = [];
foreach ($allData as $item) {
    $key = $item['shift'] . ' - ' . $item['name'];
    if (!isset($shiftRankings[$key])) {
        $shiftRankings[$key] = [
            'shift' => $item['shift'],
            'name' => $item['name'],
            'daily' => ['count' => 0, 'conversion_rate' => 0],
            'weekly' => ['count' => 0, 'conversion_rate' => 0],
            'monthly' => ['count' => 0, 'conversion_rate' => 0],
            'quarterly' => ['count' => 0, 'conversion_rate' => 0],
            'yearly' => ['count' => 0, 'conversion_rate' => 0],
            'daily_items' => [],
            'weekly_items' => [],
            'monthly_items' => [],
            'quarterly_items' => [],
            'yearly_items' => []
        ];
    }
    
    // 计算时间范围
    $itemDate = new DateTime($item['date']);
    $today = new DateTime();
    $diff = $today->diff($itemDate);
    
    // 单日
    if ($diff->days === 0) {
        $shiftRankings[$key]['daily']['count'] += $item['breakthrough_count'];
        $shiftRankings[$key]['daily_items'][] = $item;
    }
    
    // 单周
    if ($diff->days <= 7) {
        $shiftRankings[$key]['weekly']['count'] += $item['breakthrough_count'];
        $shiftRankings[$key]['weekly_items'][] = $item;
    }
    
    // 单月
    if ($diff->m === 0 && $diff->y === 0) {
        $shiftRankings[$key]['monthly']['count'] += $item['breakthrough_count'];
        $shiftRankings[$key]['monthly_items'][] = $item;
    }
    
    // 季度
    if ($diff->m <= 3 && $diff->y === 0) {
        $shiftRankings[$key]['quarterly']['count'] += $item['breakthrough_count'];
        $shiftRankings[$key]['quarterly_items'][] = $item;
    }
    
    // 单年
    if ($diff->y === 0) {
        $shiftRankings[$key]['yearly']['count'] += $item['breakthrough_count'];
        $shiftRankings[$key]['yearly_items'][] = $item;
    }
}

// 计算班次平均转化率
foreach ($shiftRankings as &$ranking) {
    foreach (['daily', 'weekly', 'monthly', 'quarterly', 'yearly'] as $period) {
        $items = $ranking[$period . '_items'];
        if (!empty($items)) {
            $totalRate = 0;
            foreach ($items as $item) {
                $totalRate += $item['conversion_rate'];
            }
            $ranking[$period]['conversion_rate'] = $totalRate / count($items);
        }
    }
    unset($ranking['daily_items']);
    unset($ranking['weekly_items']);
    unset($ranking['monthly_items']);
    unset($ranking['quarterly_items']);
    unset($ranking['yearly_items']);
}

// 复制数组进行排序
$shiftDailyRankings = $shiftWeeklyRankings = $shiftMonthlyRankings = $shiftQuarterlyRankings = $shiftYearlyRankings = $shiftRankings;

// 按不同时间段排序
sortRankings($shiftDailyRankings, 'daily');
sortRankings($shiftWeeklyRankings, 'weekly');
sortRankings($shiftMonthlyRankings, 'monthly');
sortRankings($shiftQuarterlyRankings, 'quarterly');
sortRankings($shiftYearlyRankings, 'yearly');

// 按月分组数据
$monthlyData = [];
foreach ($allData as $item) {
    $month = date('Y-m', strtotime($item['date']));
    if (!isset($monthlyData[$month])) {
        $monthlyData[$month] = [];
    }
    $monthlyData[$month][] = $item;
}

// 获取最近12个月的数据
$recentMonths = [];
$currentMonth = date('Y-m');
for ($i = 0; $i < 12; $i++) {
    $month = date('Y-m', strtotime("-$i months"));
    $recentMonths[$month] = isset($monthlyData[$month]) ? $monthlyData[$month] : [];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>打造邹区最强售前天团统计表</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:hover { background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 8px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background-color: #45a049; }
        .btn-danger { background-color: #f44336; }
        .btn-danger:hover { background-color: #d32f2f; }
        .btn-edit { background-color: #008CBA; }
        .btn-edit:hover { background-color: #007B9A; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 15px; text-decoration: none; color: #333; }
        .nav a:hover { text-decoration: underline; }
        .login-form { max-width: 400px; margin: 0 auto; }
        .error { color: #f44336; margin-bottom: 10px; }
        .stats-container { display: flex; flex-wrap: wrap; gap: 20px; }
        .stat-box { flex: 1; min-width: 300px; border: 1px solid #ddd; padding: 15px; border-radius: 4px; }
        .show-more { margin-top: 10px; text-align: center; }
        .show-more a { color: #008CBA; cursor: pointer; }
        .edit-form { margin-top: 20px; }
        .tab-container { margin-bottom: 20px; }
        .tab { display: inline-block; padding: 10px 15px; background-color: #f2f2f2; border: 1px solid #ddd; cursor: pointer; margin-right: -1px; }
        .tab.active { background-color: #4CAF50; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <div class="container">
        <h1>打造邹区最强售前天团统计表</h1>
        
        <?php if (!isLoggedIn()): ?>
            <!-- 未登录 - 显示登录链接 -->
            <div class="nav">
                <a href="index.php?action=login">登录管理</a>
            </div>
        <?php else: ?>
            <!-- 已登录 - 显示管理导航 -->
            <div class="nav">
                <a href="index.php">首页</a>
                <a href="index.php?action=add">添加数据</a>
                <a href="index.php?action=logout">退出登录</a>
            </div>
        <?php endif; ?>
        
        <!-- 登录表单 -->
        <?php if (isset($_GET['action']) && $_GET['action'] === 'login' && !isLoggedIn()): ?>
            <div class="login-form">
                <h2>管理员登录</h2>
                <?php if (isset($loginError)): ?>
                    <div class="error"><?php echo $loginError; ?></div>
                <?php endif; ?>
                <form method="post">
                    <div class="form-group">
                        <label for="username">用户名:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">密码:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" name="login" class="btn">登录</button>
                </form>
            </div>
        <?php elseif (isset($_GET['action']) && $_GET['action'] === 'add' && isLoggedIn()): ?>
            <!-- 添加数据表单 -->
            <h2>添加转化率突破记录</h2>
            <form method="post">
                <div class="form-group">
                    <label for="date">日期:</label>
                    <input type="date" id="date" name="date" value="<?php echo $currentDate; ?>" required>
                </div>
                <div class="form-group">
                    <label for="name">姓名:</label>
                    <input type="text" id="name" name="name" list="names" required>
                    <datalist id="names">
                        <?php foreach ($uniqueNames as $name): ?>
                            <option value="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label for="shop">店铺名称:</label>
                    <select id="shop" name="shop" required>
                        <option value="">请选择店铺</option>
                        <?php foreach ($shopNames as $shop): ?>
                            <option value="<?php echo htmlspecialchars($shop); ?>"><?php echo htmlspecialchars($shop); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="shift">班次:</label>
                    <select id="shift" name="shift" required>
                        <option value="">请选择班次</option>
                        <?php foreach ($shifts as $shift): ?>
                            <option value="<?php echo htmlspecialchars($shift); ?>"><?php echo htmlspecialchars($shift); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="conversion_rate">转化率 (%):</label>
                    <input type="number" step="0.01" id="conversion_rate" name="conversion_rate" required>
                </div>
                <div class="form-group">
                    <label for="breakthrough_count">突破次数:</label>
                    <input type="number" id="breakthrough_count" name="breakthrough_count" required>
                </div>
                <button type="submit" name="add" class="btn">添加</button>
            </form>
        <?php elseif (isset($_GET['action']) && $_GET['action'] === 'edit' && isLoggedIn()): ?>
            <!-- 编辑数据表单 -->
            <?php
            $editId = $_GET['id'];
            $editItem = null;
            
            foreach ($allData as $item) {
                if ($item['id'] === $editId) {
                    $editItem = $item;
                    break;
                }
            }
            
            if (!$editItem):
                echo '<p>未找到该记录</p>';
            else:
            ?>
                <h2>编辑转化率突破记录</h2>
                <form method="post">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editItem['id']); ?>">
                    <div class="form-group">
                        <label for="date">日期:</label>
                        <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($editItem['date']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="name">姓名:</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($editItem['name']); ?>" list="names" required>
                        <datalist id="names">
                            <?php foreach ($uniqueNames as $name): ?>
                                <option value="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label for="shop">店铺名称:</label>
                        <select id="shop" name="shop" required>
                            <?php foreach ($shopNames as $shop): ?>
                                <option value="<?php echo htmlspecialchars($shop); ?>" <?php echo ($editItem['shop'] === $shop) ? 'selected' : ''; ?>><?php echo htmlspecialchars($shop); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="shift">班次:</label>
                        <select id="shift" name="shift" required>
                            <?php foreach ($shifts as $shift): ?>
                                <option value="<?php echo htmlspecialchars($shift); ?>" <?php echo ($editItem['shift'] === $shift) ? 'selected' : ''; ?>><?php echo htmlspecialchars($shift); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="conversion_rate">转化率 (%):</label>
                        <input type="number" step="0.01" id="conversion_rate" name="conversion_rate" value="<?php echo htmlspecialchars($editItem['conversion_rate']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="breakthrough_count">突破次数:</label>
                        <input type="number" id="breakthrough_count" name="breakthrough_count" value="<?php echo htmlspecialchars($editItem['breakthrough_count']); ?>" required>
                    </div>
                    <button type="submit" name="edit" class="btn">保存</button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <!-- 首页 - 最近数据 -->
            <h2>最近记录</h2>
            <table>
                <tr>
                    <th>日期</th>
                    <th>姓名</th>
                    <th>店铺名称</th>
                    <th>班次</th>
                    <th>转化率 (%)</th>
                    <th>突破次数</th>
                    <?php if (isLoggedIn()): ?>
                        <th>操作</th>
                    <?php endif; ?>
                </tr>
                <?php foreach ($recentData as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['date']); ?></td>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo htmlspecialchars($item['shop']); ?></td>
                        <td><?php echo htmlspecialchars($item['shift']); ?></td>
                        <td><?php echo htmlspecialchars(number_format($item['conversion_rate'], 2)); ?></td>
                        <td><?php echo htmlspecialchars($item['breakthrough_count']); ?></td>
                        <?php if (isLoggedIn()): ?>
                            <td>
                                <a href="index.php?action=edit&id=<?php echo htmlspecialchars($item['id']); ?>" class="btn btn-edit">编辑</a>
                                <a href="index.php?action=delete&id=<?php echo htmlspecialchars($item['id']); ?>" class="btn btn-danger" onclick="return confirm('确定要删除这条记录吗?')">删除</a>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </table>
            
            <!-- 统计数据选项卡 -->
            <h2>统计数据</h2>
            
            <div class="tab-container">
                <div class="tab active" data-tab="individual">个人突破次数排行榜</div>
                <div class="tab" data-tab="shift">班次突破次数排行榜</div>
            </div>
            
            <!-- 个人突破次数排行榜 -->
            <div class="tab-content active" id="individual">
                <div class="tab-container">
                    <div class="tab active" data-period="daily">单日</div>
                    <div class="tab" data-period="weekly">单周</div>
                    <div class="tab" data-period="monthly">单月</div>
                    <div class="tab" data-period="quarterly">季度</div>
                    <div class="tab" data-period="yearly">单年</div>
                </div>
                
                <div class="period-content active" id="individual-daily">
                    <h3>单日突破次数排行榜</h3>
                    <table>
                        <tr>
                            <th>排名</th>
                            <th>店铺</th>
                            <th>姓名</th>
                            <th>突破次数</th>
                            <th>平均转化率 (%)</th>
                        </tr>
                        <?php $rank = 1; foreach (array_slice($dailyRankings, 0, 10) as $item): ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($item['shop']); ?></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['daily']['count']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($item['daily']['conversion_rate'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                
                <div class="period-content" id="individual-weekly">
                    <h3>单周突破次数排行榜</h3>
                    <table>
                        <tr>
                            <th>排名</th>
                            <th>店铺</th>
                            <th>姓名</th>
                            <th>突破次数</th>
                            <th>平均转化率 (%)</th>
                        </tr>
                        <?php $rank = 1; foreach (array_slice($weeklyRankings, 0, 10) as $item): ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($item['shop']); ?></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['weekly']['count']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($item['weekly']['conversion_rate'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                
                <div class="period-content" id="individual-monthly">
                    <h3>单月突破次数排行榜</h3>
                    <table>
                        <tr>
                            <th>排名</th>
                            <th>店铺</th>
                            <th>姓名</th>
                            <th>突破次数</th>
                            <th>平均转化率 (%)</th>
                        </tr>
                        <?php $rank = 1; foreach (array_slice($monthlyRankings, 0, 10) as $item): ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($item['shop']); ?></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['monthly']['count']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($item['monthly']['conversion_rate'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                
                <div class="period-content" id="individual-quarterly">
                    <h3>季度突破次数排行榜</h3>
                    <table>
                        <tr>
                            <th>排名</th>
                            <th>店铺</th>
                            <th>姓名</th>
                            <th>突破次数</th>
                            <th>平均转化率 (%)</th>
                        </tr>
                        <?php $rank = 1; foreach (array_slice($quarterlyRankings, 0, 10) as $item): ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($item['shop']); ?></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['quarterly']['count']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($item['quarterly']['conversion_rate'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                
                <div class="period-content" id="individual-yearly">
                    <h3>单年突破次数排行榜</h3>
                    <table>
                        <tr>
                            <th>排名</th>
                            <th>店铺</th>
                            <th>姓名</th>
                            <th>突破次数</th>
                            <th>平均转化率 (%)</th>
                        </tr>
                        <?php $rank = 1; foreach (array_slice($yearlyRankings, 0, 10) as $item): ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($item['shop']); ?></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['yearly']['count']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($item['yearly']['conversion_rate'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            
            <!-- 班次突破次数排行榜 -->
            <div class="tab-content" id="shift">
                <div class="tab-container">
                    <div class="tab active" data-period="daily">单日</div>
                    <div class="tab" data-period="weekly">单周</div>
                    <div class="tab" data-period="monthly">单月</div>
                    <div class="tab" data-period="quarterly">季度</div>
                    <div class="tab" data-period="yearly">单年</div>
                </div>
                
                <div class="period-content active" id="shift-daily">
                    <h3>单日突破次数排行榜</h3>
                    <table>
                        <tr>
                            <th>排名</th>
                            <th>班次</th>
                            <th>姓名</th>
                            <th>突破次数</th>
                            <th>平均转化率 (%)</th>
                        </tr>
                        <?php $rank = 1; foreach (array_slice($shiftDailyRankings, 0, 10) as $item): ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($item['shift']); ?></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['daily']['count']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($item['daily']['conversion_rate'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                
                <div class="period-content" id="shift-weekly">
                    <h3>单周突破次数排行榜</h3>
                    <table>
                        <tr>
                            <th>排名</th>
                            <th>班次</th>
                            <th>姓名</th>
                            <th>突破次数</th>
                            <th>平均转化率 (%)</th>
                        </tr>
                        <?php $rank = 1; foreach (array_slice($shiftWeeklyRankings, 0, 10) as $item): ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($item['shift']); ?></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['weekly']['count']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($item['weekly']['conversion_rate'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                
                <div class="period-content" id="shift-monthly">
                    <h3>单月突破次数排行榜</h3>
                    <table>
                        <tr>
                            <th>排名</th>
                            <th>班次</th>
                            <th>姓名</th>
                            <th>突破次数</th>
                            <th>平均转化率 (%)</th>
                        </tr>
                        <?php $rank = 1; foreach (array_slice($shiftMonthlyRankings, 0, 10) as $item): ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($item['shift']); ?></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['monthly']['count']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($item['monthly']['conversion_rate'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                
                <div class="period-content" id="shift-quarterly">
                    <h3>季度突破次数排行榜</h3>
                    <table>
                        <tr>
                            <th>排名</th>
                            <th>班次</th>
                            <th>姓名</th>
                            <th>突破次数</th>
                            <th>平均转化率 (%)</th>
                        </tr>
                        <?php $rank = 1; foreach (array_slice($shiftQuarterlyRankings, 0, 10) as $item): ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($item['shift']); ?></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['quarterly']['count']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($item['quarterly']['conversion_rate'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                
                <div class="period-content" id="shift-yearly">
                    <h3>单年突破次数排行榜</h3>
                    <table>
                        <tr>
                            <th>排名</th>
                            <th>班次</th>
                            <th>姓名</th>
                            <th>突破次数</th>
                            <th>平均转化率 (%)</th>
                        </tr>
                        <?php $rank = 1; foreach (array_slice($shiftYearlyRankings, 0, 10) as $item): ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><?php echo htmlspecialchars($item['shift']); ?></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['yearly']['count']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($item['yearly']['conversion_rate'], 2)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            
            <!-- 最近12个月趋势图 -->
            <div class="stat-box" style="flex-basis: 100%;">
                <h3>最近12个月突破次数趋势</h3>
                <div class="trend-graph">
                    <table>
                        <tr>
                            <th>月份</th>
                            <th>突破总次数</th>
                            <th>趋势图</th>
                        </tr>
                        <?php foreach ($recentMonths as $month => $data): ?>
                            <?php
                            $totalBreakthrough = 0;
                            foreach ($data as $item) {
                                $totalBreakthrough += $item['breakthrough_count'];
                            }
                            
                            // 计算百分比宽度 (最大为100%)
                            $maxBreakthrough = 0;
                            foreach ($recentMonths as $m => $d) {
                                $monthTotal = 0;
                                foreach ($d as $item) {
                                    $monthTotal += $item['breakthrough_count'];
                                }
                                $maxBreakthrough = max($maxBreakthrough, $monthTotal);
                            }
                            
                            $width = ($maxBreakthrough > 0) ? min(100, ($totalBreakthrough / $maxBreakthrough) * 100) : 0;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars(date('Y年m月', strtotime($month))); ?></td>
                                <td><?php echo htmlspecialchars($totalBreakthrough); ?></td>
                                <td>
                                    <div style="height: 20px; background-color: #ddd; width: 100%;">
                                        <div style="height: 100%; background-color: #4CAF50; width: <?php echo $width; ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // 统计数据切换函数
        document.addEventListener('DOMContentLoaded', function() {
            // 标签页切换
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    // 移除所有活动状态
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // 添加当前活动状态
                    tab.classList.add('active');
                    const tabId = tab.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                    
                    // 重置时间段标签状态
                    const periodTabs = document.querySelectorAll('[data-period]');
                    const periodContents = document.querySelectorAll('.period-content');
                    
                    periodTabs.forEach((periodTab, index) => {
                        if (index === 0) {
                            periodTab.classList.add('active');
                        } else {
                            periodTab.classList.remove('active');
                        }
                    });
                    
                    periodContents.forEach((periodContent, index) => {
                        if (index === 0) {
                            periodContent.classList.add('active');
                        } else {
                            periodContent.classList.remove('active');
                        }
                    });
                });
            });
            
            // 时间段标签切换
            const periodTabs = document.querySelectorAll('[data-period]');
            const periodContents = document.querySelectorAll('.period-content');
            
            periodTabs.forEach(periodTab => {
                periodTab.addEventListener('click', () => {
                    // 移除所有活动状态
                    periodTabs.forEach(t => t.classList.remove('active'));
                    periodContents.forEach(content => content.classList.remove('active'));
                    
                    // 添加当前活动状态
                    periodTab.classList.add('active');
                    const period = periodTab.getAttribute('data-period');
                    const tabType = periodTab.closest('.tab-content').id;
                    document.getElementById(`${tabType}-${period}`).classList.add('active');
                });
            });
        });
    </script>
</body>
</html>    
