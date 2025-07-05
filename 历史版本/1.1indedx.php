<?php
session_start();

// 配置
define('DATA_FILE', 'conversion_data.txt');
define('USERS_FILE', 'users.txt');
define('DEFAULT_PASSWORD', password_hash('admin123', PASSWORD_DEFAULT));

// 初始化文件
if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, json_encode([]));
}
if (!file_exists(USERS_FILE)) {
    file_put_contents(USERS_FILE, json_encode(['admin' => DEFAULT_PASSWORD]));
}

// 数据模型
function read_data() {
    return json_decode(file_get_contents(DATA_FILE), true) ?: [];
}

function save_data($data) {
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

function read_users() {
    return json_decode(file_get_contents(USERS_FILE), true) ?: [];
}

function save_users($users) {
    file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
}

// 认证功能
function is_admin() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

function authenticate($username, $password) {
    $users = read_users();
    if (isset($users[$username]) && password_verify($password, $users[$username])) {
        $_SESSION['admin'] = true;
        return true;
    }
    return false;
}

// 数据处理
function add_entry($shop, $shift, $date, $name, $conversion) {
    $data = read_data();
    $data[] = [
        'id' => uniqid(),
        'shop' => $shop,
        'shift' => $shift,
        'date' => $date,
        'name' => $name,
        'conversion' => (float)$conversion
    ];
    save_data($data);
}

function update_entry($id, $shop, $shift, $date, $name, $conversion) {
    $data = read_data();
    foreach ($data as &$entry) {
        if ($entry['id'] === $id) {
            $entry['shop'] = $shop;
            $entry['shift'] = $shift;
            $entry['date'] = $date;
            $entry['name'] = $name;
            $entry['conversion'] = (float)$conversion;
            break;
        }
    }
    save_data($data);
}

function delete_entry($id) {
    $data = read_data();
    $data = array_filter($data, function($entry) use ($id) {
        return $entry['id'] !== $id;
    });
    save_data($data);
}

// 统计功能
function calculate_ranking($data, $group_by = 'name') {
    $results = [];
    foreach ($data as $entry) {
        $key = $entry[$group_by];
        if (!isset($results[$key])) {
            $results[$key] = ['count' => 0, 'total' => 0, 'shifts' => []];
        }
        $results[$key]['count']++;
        $results[$key]['total'] += $entry['conversion'];
        if (!in_array($entry['shift'], $results[$key]['shifts'])) {
            $results[$key]['shifts'][] = $entry['shift'];
        }
    }
    
    foreach ($results as &$result) {
        $result['average'] = $result['count'] > 0 ? $result['total'] / $result['count'] : 0;
        $result['shifts'] = implode('、', $result['shifts']);
    }
    
    uasort($results, function($a, $b) {
        return $b['average'] <=> $a['average'];
    });
    
    return $results;
}

// 处理请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        if (authenticate($_POST['username'], $_POST['password'])) {
            header('Location: index.php');
            exit;
        } else {
            $error = '认证失败';
        }
    } elseif (isset($_POST['logout'])) {
        session_destroy();
        header('Location: index.php');
        exit;
    } elseif (isset($_POST['add'])) {
        add_entry(
            $_POST['shop'],
            $_POST['shift'],
            $_POST['date'],
            $_POST['name'],
            $_POST['conversion']
        );
        header('Location: index.php');
        exit;
    } elseif (isset($_POST['update'])) {
        update_entry(
            $_POST['id'],
            $_POST['shop'],
            $_POST['shift'],
            $_POST['date'],
            $_POST['name'],
            $_POST['conversion']
        );
        header('Location: index.php');
        exit;
    } elseif (isset($_POST['delete'])) {
        delete_entry($_POST['id']);
        header('Location: index.php');
        exit;
    }
}

// 获取数据
$data = read_data();
$shops = ['天猫尚显', '天猫名珠', '淘宝尚显', '拼多多尚显', '拼多多尚宇', '拼多多顾家'];
$shifts = ['白班', '中班', '晚班'];
$names = array_unique(array_column($data, 'name'));
sort($names);

// 今日日期
$today = date('Y-m-d');

// 视图
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>邹区最强售前天团转化率统计</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { color: #333; }
        .container { max-width: 1200px; margin: 0 auto; }
        .section { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .form-group { margin-bottom: 10px; }
        .form-group label { display: block; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 8px; box-sizing: border-box; }
        table { width: 100%; border-collapse: collapse; }
        table, th, td { border: 1px solid #ddd; padding: 8px; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .stats-section { display: flex; flex-wrap: wrap; gap: 20px; }
        .stats-box { flex: 1 1 300px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
        .login-form { max-width: 400px; margin: 50px auto; }
        .button { padding: 8px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .button:hover { background-color: #45a049; }
        .button-danger { background-color: #f44336; }
        .button-danger:hover { background-color: #d32f2f; }
        .button-secondary { background-color: #555555; }
        .button-secondary:hover { background-color: #333333; }
        .admin-controls { text-align: right; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>邹区最强售前天团转化率统计</h2>
        
        <?php if (is_admin()): ?>
            <div class="admin-controls">
                <form method="post" style="display: inline;">
                    <button type="submit" name="logout" class="button button-secondary">退出登录</button>
                </form>
            </div>
        <?php else: ?>
            <div class="admin-controls">
                <button onclick="document.getElementById('loginModal').style.display='block'" class="button button-secondary">管理员登录</button>
            </div>
        <?php endif; ?>
        
        <div class="section">
            <h3>录入数据</h3>
            <form method="post">
                <div class="form-group">
                    <label for="shop">店铺名称:</label>
                    <select id="shop" name="shop" required>
                        <option value="">请选择</option>
                        <?php foreach ($shops as $shop): ?>
                            <option value="<?php echo htmlspecialchars($shop); ?>"><?php echo htmlspecialchars($shop); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="shift">班次:</label>
                    <select id="shift" name="shift" required>
                        <option value="">请选择</option>
                        <?php foreach ($shifts as $shift): ?>
                            <option value="<?php echo htmlspecialchars($shift); ?>"><?php echo htmlspecialchars($shift); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date">日期:</label>
                    <input type="date" id="date" name="date" value="<?php echo $today; ?>" required>
                </div>
                <div class="form-group">
                    <label for="name">姓名:</label>
                    <input type="text" id="name" name="name" list="names" required>
                    <datalist id="names">
                        <?php foreach ($names as $name): ?>
                            <option value="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label for="conversion">转化率 (%):</label>
                    <input type="number" step="0.01" id="conversion" name="conversion" min="0" max="100" required>
                </div>
                <button type="submit" name="add" class="button">添加数据</button>
            </form>
        </div>
        
        <div class="section">
            <h3>数据列表</h3>
            <table>
                <thead>
                    <tr>
                        <th>店铺名称</th>
                        <th>班次</th>
                        <th>日期</th>
                        <th>姓名</th>
                        <th>转化率 (%)</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $entry): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($entry['shop']); ?></td>
                            <td><?php echo htmlspecialchars($entry['shift']); ?></td>
                            <td><?php echo htmlspecialchars($entry['date']); ?></td>
                            <td><?php echo htmlspecialchars($entry['name']); ?></td>
                            <td><?php echo number_format($entry['conversion'], 2); ?></td>
                            <td>
                                <?php if (is_admin()): ?>
                                    <button onclick="editEntry('<?php echo $entry['id']; ?>', '<?php echo htmlspecialchars($entry['shop']); ?>', '<?php echo htmlspecialchars($entry['shift']); ?>', '<?php echo $entry['date']; ?>', '<?php echo htmlspecialchars($entry['name']); ?>', '<?php echo $entry['conversion']; ?>')" class="button">编辑</button>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="id" value="<?php echo $entry['id']; ?>">
                                        <button type="submit" name="delete" class="button button-danger">删除</button>
                                    </form>
                                <?php else: ?>
                                    <span class="button button-secondary" disabled>请先登录</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h3>数据统计</h3>
            
            <div class="form-group">
                <label for="stats_period_type">统计周期:</label>
                <select id="stats_period_type" onchange="updatePeriodOptions()">
                    <option value="day">按日</option>
                    <option value="week">按周</option>
                    <option value="month">按月</option>
                    <option value="quarter">按季度</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="stats_period_value">选择周期:</label>
                <select id="stats_period_value">
                    <?php
                    // 生成日期选项
                    $period_options = [];
                    
                    // 日
                    $days = [];
                    foreach ($data as $entry) {
                        $days[$entry['date']] = $entry['date'];
                    }
                    // 确保今天也在选项中
                    if (!isset($days[$today])) {
                        $days[$today] = $today;
                    }
                    sort($days);
                    $period_options['day'] = $days;
                    
                    // 周
                    $weeks = [];
                    foreach ($data as $entry) {
                        $date = new DateTime($entry['date']);
                        $week = $date->format('W');
                        $year = $date->format('Y');
                        $weeks["{$week}-{$year}"] = "第 {$week} 周 ({$year})";
                    }
                    // 添加当前周
                    $currentWeek = date('W');
                    $currentYear = date('Y');
                    if (!isset($weeks["{$currentWeek}-{$currentYear}"])) {
                        $weeks["{$currentWeek}-{$currentYear}"] = "第 {$currentWeek} 周 ({$currentYear})";
                    }
                    ksort($weeks);
                    $period_options['week'] = $weeks;
                    
                    // 月
                    $months = [];
                    foreach ($data as $entry) {
                        $date = new DateTime($entry['date']);
                        $month = $date->format('Y-m');
                        $months[$month] = $date->format('Y年m月');
                    }
                    // 添加当前月
                    $currentMonth = date('Y-m');
                    if (!isset($months[$currentMonth])) {
                        $months[$currentMonth] = date('Y年m月');
                    }
                    ksort($months);
                    $period_options['month'] = $months;
                    
                    // 季度
                    $quarters = [];
                    foreach ($data as $entry) {
                        $date = new DateTime($entry['date']);
                        $quarter = ceil($date->format('n') / 3);
                        $year = $date->format('Y');
                        $quarters["{$quarter}-{$year}"] = "第 {$quarter} 季度 ({$year})";
                    }
                    // 添加当前季度
                    $currentQuarter = ceil(date('n') / 3);
                    $currentYear = date('Y');
                    if (!isset($quarters["{$currentQuarter}-{$currentYear}"])) {
                        $quarters["{$currentQuarter}-{$currentYear}"] = "第 {$currentQuarter} 季度 ({$currentYear})";
                    }
                    ksort($quarters);
                    $period_options['quarter'] = $quarters;
                    
                    // 输出默认选项（今日）
                    foreach ($period_options['day'] as $value => $label) {
                        echo "<option value=\"{$value}\">{$label}</option>";
                    }
                    ?>
                </select>
            </div>
            
            <button onclick="updateStatistics()" class="button">更新统计</button>
            
            <div id="stats_results" class="stats-section">
                <!-- 统计结果将在这里显示 -->
            </div>
        </div>
    </div>
    
    <!-- 编辑模态框 -->
    <div id="editModal" style="display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
        <div style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px;">
            <h3>编辑数据</h3>
            <form method="post" id="editForm">
                <input type="hidden" id="edit_id" name="id">
                <div class="form-group">
                    <label for="edit_shop">店铺名称:</label>
                    <select id="edit_shop" name="shop" required>
                        <?php foreach ($shops as $shop): ?>
                            <option value="<?php echo htmlspecialchars($shop); ?>"><?php echo htmlspecialchars($shop); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_shift">班次:</label>
                    <select id="edit_shift" name="shift" required>
                        <?php foreach ($shifts as $shift): ?>
                            <option value="<?php echo htmlspecialchars($shift); ?>"><?php echo htmlspecialchars($shift); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_date">日期:</label>
                    <input type="date" id="edit_date" name="date" required>
                </div>
                <div class="form-group">
                    <label for="edit_name">姓名:</label>
                    <input type="text" id="edit_name" name="name" list="edit_names" required>
                    <datalist id="edit_names">
                        <?php foreach ($names as $name): ?>
                            <option value="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label for="edit_conversion">转化率 (%):</label>
                    <input type="number" step="0.01" id="edit_conversion" name="conversion" min="0" max="100" required>
                </div>
                <button type="submit" name="update" class="button">保存修改</button>
                <button type="button" onclick="closeEditModal()" class="button button-secondary">取消</button>
            </form>
        </div>
    </div>
    
    <!-- 登录模态框 -->
    <div id="loginModal" style="display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
        <div style="background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 400px;">
            <h3>管理员登录</h3>
            <form method="post">
                <div class="form-group">
                    <label for="username">用户名:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">密码:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <?php if (isset($error)): ?>
                    <div style="color: red; margin-bottom: 10px;"><?php echo $error; ?></div>
                <?php endif; ?>
                <button type="submit" name="login" class="button">登录</button>
                <button type="button" onclick="document.getElementById('loginModal').style.display='none'" class="button button-secondary">取消</button>
            </form>
        </div>
    </div>
    
    <script>
        function editEntry(id, shop, shift, date, name, conversion) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_shop').value = shop;
            document.getElementById('edit_shift').value = shift;
            document.getElementById('edit_date').value = date;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_conversion').value = conversion;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function updatePeriodOptions() {
            const type = document.getElementById('stats_period_type').value;
            const valueSelect = document.getElementById('stats_period_value');
            
            // 清空当前选项
            valueSelect.innerHTML = '';
            
            // 根据选择的周期类型添加对应的选项
            <?php
            // 输出JavaScript对象
            echo 'const periodOptions = ' . json_encode($period_options) . ';';
            ?>
            
            for (const [value, label] of Object.entries(periodOptions[type])) {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                valueSelect.appendChild(option);
            }
            
            // 确保有选中的值
            if (valueSelect.options.length > 0) {
                valueSelect.selectedIndex = 0;
            }
        }
        
        function updateStatistics() {
            const type = document.getElementById('stats_period_type').value;
            const value = document.getElementById('stats_period_value').value;
            
            // 从PHP获取数据
            const data = <?php echo json_encode($data); ?>;
            
            // 筛选数据
            let filteredData;
            if (type === 'day') {
                filteredData = data.filter(entry => entry.date === value);
            } else if (type === 'week') {
                const [week, year] = value.split('-');
                filteredData = data.filter(entry => {
                    const date = new Date(entry.date);
                    const entryWeek = date.getWeek();
                    const entryYear = date.getFullYear();
                    return entryWeek === parseInt(week) && entryYear === parseInt(year);
                });
            } else if (type === 'month') {
                filteredData = data.filter(entry => {
                    const [entryYear, entryMonth] = entry.date.split('-');
                    const [selectedYear, selectedMonth] = value.split('-');
                    return entryYear === selectedYear && entryMonth === selectedMonth;
                });
            } else if (type === 'quarter') {
                const [quarter, year] = value.split('-');
                filteredData = data.filter(entry => {
                    const [entryYear, entryMonth] = entry.date.split('-');
                    const entryQuarter = Math.ceil(parseInt(entryMonth) / 3);
                    return entryQuarter === parseInt(quarter) && entryYear === year;
                });
            }
            
            // 计算统计结果
            function calculateRanking(data, groupBy) {
                if (!data || data.length === 0) return [];
                
                const results = {};
                data.forEach(entry => {
                    const key = entry[groupBy];
                    if (!results[key]) {
                        results[key] = { 
                            count: 0, 
                            total: 0,
                            shifts: {}
                        };
                    }
                    results[key].count++;
                    results[key].total += parseFloat(entry.conversion);
                    results[key].shifts[entry.shift] = true;
                });
                
                return Object.entries(results)
                    .map(([name, stats]) => ({
                        name,
                        average: stats.count > 0 ? stats.total / stats.count : 0,
                        shifts: Object.keys(stats.shifts).join('、')
                    }))
                    .sort((a, b) => b.average - a.average)
                    .map((item, index) => ({...item, rank: index + 1}));
            }
            
            const personalRanking = calculateRanking(filteredData, 'name');
            const shiftRanking = calculateRanking(filteredData, 'shift');
            const overallRanking = calculateRanking(filteredData, 'name');
            
            // 显示统计结果
            const statsResults = document.getElementById('stats_results');
            statsResults.innerHTML = '';
            
            // 周期名称
            const periodNames = {
                day: '日',
                week: '周',
                month: '月',
                quarter: '季度'
            };
            const periodName = periodNames[type] || type;
            
            // 个人排行榜
            const personalBox = document.createElement('div');
            personalBox.className = 'stats-box';
            personalBox.innerHTML = `
                <h4>个人排行榜 (${periodName})</h4>
                ${personalRanking.length > 0 ? `
                <table>
                    <thead>
                        <tr>
                            <th>排名</th>
                            <th>姓名</th>
                            <th>班次</th>
                            <th>平均转化率 (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${personalRanking.map(rank => `
                            <tr>
                                <td>${rank.rank}</td>
                                <td>${rank.name}</td>
                                <td>${rank.shifts}</td>
                                <td>${rank.average.toFixed(2)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>` : '<p>暂无数据</p>'}
            `;
            statsResults.appendChild(personalBox);
            
            // 班次排行榜
            const shiftBox = document.createElement('div');
            shiftBox.className = 'stats-box';
            shiftBox.innerHTML = `
                <h4>班次排行榜 (${periodName})</h4>
                ${shiftRanking.length > 0 ? `
                <table>
                    <thead>
                        <tr>
                            <th>排名</th>
                            <th>班次</th>
                            <th>参与人数</th>
                            <th>平均转化率 (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${shiftRanking.map(rank => `
                            <tr>
                                <td>${rank.rank}</td>
                                <td>${rank.name}</td>
                                <td>${rank.count}</td>
                                <td>${rank.average.toFixed(2)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>` : '<p>暂无数据</p>'}
            `;
            statsResults.appendChild(shiftBox);
            
            // 公司所有人排行
            const overallBox = document.createElement('div');
            overallBox.className = 'stats-box';
            overallBox.innerHTML = `
                <h4>公司所有人排行 (${periodName})</h4>
                ${overallRanking.length > 0 ? `
                <table>
                    <thead>
                        <tr>
                            <th>排名</th>
                            <th>姓名</th>
                            <th>班次</th>
                            <th>平均转化率 (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${overallRanking.map(rank => `
                            <tr>
                                <td>${rank.rank}</td>
                                <td>${rank.name}</td>
                                <td>${rank.shifts}</td>
                                <td>${rank.average.toFixed(2)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>` : '<p>暂无数据</p>'}
            `;
            statsResults.appendChild(overallBox);
        }
        
        // 为Date对象添加getWeek方法
        Date.prototype.getWeek = function() {
            const date = new Date(this.getTime());
            date.setHours(0, 0, 0, 0);
            // 周四在当前周中
            date.setDate(date.getDate() + 3 - (date.getDay() || 7));
            const week1 = new Date(date.getFullYear(), 0, 4);
            return 1 + Math.round(((date - week1) / 86400000 - 3 + (week1.getDay() || 7)) / 7);
        };
        
        // 页面加载时初始化统计
        window.onload = updateStatistics;
    </script>
</body>
</html>    
