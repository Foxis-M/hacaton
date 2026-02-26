<?php
// Translation file for Russian language

function getTranslation($key, $lang = 'ru') {
    $translations = [
        'ru' => [
            // Welcome messages
            'Good morning' => 'Доброе утро',
            'Good afternoon' => 'Добрый день',
            'Good evening' => 'Добрый вечер',
            
            // Role descriptions
            'Manage students, teachers, and system settings from your administrator dashboard.' => 'Управляйте студентами, преподавателями и настройками системы из панели администратора.',
            'Manage your classes, grades, and access AI-powered teaching tools.' => 'Управляйте своими классами, оценками и используйте инструменты обучения с ИИ.',
            'View your grades, homework, schedule, and track your academic progress.' => 'Просматривайте свои оценки, домашние задания, расписание и отслеживайте академический прогресс.',
            
            // Navigation labels
            'User Approvals' => 'Подтверждение пользователей',
            'Student Management' => 'Управление студентами',
            'Teacher Management' => 'Управление преподавателями',
            'System Settings' => 'Настройки системы',
            'User Access Control' => 'Контроль доступа',
            'AI Schedule Generator' => 'Генератор расписания ИИ',
            'Knowledge Base' => 'База знаний',
            'Analytics' => 'Аналитика',
            'Academic Process' => 'Академический процесс',
            'My Groups' => 'Мои группы',
            'Grade Management' => 'Управление оценками',
            'AI Lesson Planner' => 'Планировщик уроков ИИ',
            'Journals' => 'Журналы',
            'My Grades' => 'Мои оценки',
            'Homework' => 'Домашнее задание',
            'AI Tests' => 'Тесты ИИ',
            'My Schedule' => 'Мое расписание',
            'Deadlines' => 'Сроки сдачи',
            'Statistics' => 'Статистика',
            
            // Dashboard stats
            'Total Students' => 'Всего студентов',
            'Total Teachers' => 'Всего преподавателей',
            'Active Courses' => 'Активные курсы',
            'System Health' => 'Состояние системы',
            'Today\'s Classes' => 'Сегодняшние занятия',
            'Pending Grades' => 'Ожидающие оценки',
            'Due tomorrow' => 'Сдать завтра',
            'In 6 classes' => 'В 6 классах',
            'Ready to generate' => 'Готово к генерации',
            'Current GPA' => 'Текущий средний балл',
            'Assignments Due' => 'Задания со сроком сдачи',
            '2 due today' => '2 сегодня',
            'Attendance' => 'Посещаемость',
            'Excellent' => 'Отлично',
            'Next Class' => 'Следующий урок',
            'Mathematics' => 'Математика',
            
            // Quick actions
            'Add Student' => 'Добавить студента',
            'Add Teacher' => 'Добавить преподавателя',
            'System Settings' => 'Настройки системы',
            'View Reports' => 'Просмотреть отчеты',
            'Create Lesson' => 'Создать урок',
            'Enter Grades' => 'Ввести оценки',
            'Generate Schedule' => 'Создать расписание',
            'View Journals' => 'Просмотр журналов',
            'View Grades' => 'Просмотр оценок',
            
            // Activities
            'New Student Registration' => 'Новая регистрация студента',
            '5 new students registered today' => '5 новых студентов зарегистрировано сегодня',
            'System Backup Completed' => 'Резервное копирование завершено',
            'Automated backup finished successfully' => 'Автоматическое резервное копирование успешно завершено',
            'Teacher Account Approved' => 'Преподавательский аккаунт одобрен',
            'Dr. Smith\'s account has been verified' => 'Аккаунт доктора Смита подтвержден',
            'Low Disk Space Warning' => 'Предупреждение о нехватке места',
            'Server storage at 85% capacity' => 'Хранилище сервера заполнено на 85%',
            'New Submission' => 'Новое представление',
            '3 students submitted homework' => '3 студента сдали домашнее задание',
            'AI Lesson Plan Ready' => 'План урока ИИ готов',
            'Your lesson plan for next week is generated' => 'Ваш план урока на следующую неделю сгенерирован',
            'Grade Deadline' => 'Крайний срок оценки',
            'Final grades due in 2 days' => 'Итоговые оценки должны быть сданы через 2 дня',
            'Parent Meeting Scheduled' => 'Запланирована встреча с родителями',
            'Meeting with Johnson family at 3 PM' => 'Встреча с семьей Джонсон в 15:00',
            'New Grade Posted' => 'Новая оценка выставлена',
            'Mathematics quiz: 92/100' => 'Викторина по математике: 92/100',
            'Homework Reminder' => 'Напоминание о домашнем задании',
            'Physics assignment due tomorrow' => 'Физика: задание должно быть сдано завтра',
            'Class Cancelled' => 'Занятие отменено',
            'History class on Friday is cancelled' => 'История: занятие в пятницу отменено',
            'Achievement Unlocked' => 'Достижение разблокировано',
            'Perfect attendance for 30 days!' => 'Идеальное посещение в течение 30 дней!',
            
            // General
            'Stable' => 'Стабильно',
            '+12%' => '+12%',
            '+3' => '+3',
            '+5' => '+5',
            '99.9%' => '99,9%',
            '+0.2' => '+0,2',
            '2 completed' => '2 завершено',
            'In 6 classes' => 'В 6 классах',
            'Due tomorrow' => 'Сдать завтра',
            '2 due today' => '2 сегодня',
            'Excellent' => 'Отлично',
            '10:30 AM' => '10:30',
            
            // Page titles
            'AI Schedule Generator' => 'Генератор расписания ИИ',
            'AI Lesson Planner' => 'Планировщик уроков ИИ',
            'AI Tests' => 'Тесты ИИ',
            'My Grades' => 'Мои оценки',
            'Student Management' => 'Управление студентами',
            'Teacher Management' => 'Управление преподавателями',
            'System Settings' => 'Настройки системы',
            'Access Control' => 'Контроль доступа',
            'Academic Process' => 'Академический процесс',
            'Knowledge Base' => 'База знаний',
            'Analytics' => 'Аналитика',
            'My Classes' => 'Мои занятия',
            'Grade Management' => 'Управление оценками',
            'Journals' => 'Журналы',
            'Homework' => 'Домашнее задание',
            'My Schedule' => 'Мое расписание',
            'Deadlines' => 'Сроки сдачи',
            'Statistics' => 'Статистика',
            'User Approvals' => 'Подтверждение пользователей',
            
            // Form labels and placeholders
            'Select Group *' => 'Выберите группу *',
            'Number of Weeks' => 'Количество недель',
            'Max Classes Per Day' => 'Максимум занятий в день',
            'First Class Starts At' => 'Первое занятие начинается в',
            'Period 1 (08:00)' => 'Период 1 (08:00)',
            'Period 2 (09:45)' => 'Период 2 (09:45)',
            'Period 3 (11:30)' => 'Период 3 (11:30)',
            'Period 4 (13:30)' => 'Период 4 (13:30)',
            'Period 5 (15:15)' => 'Период 5 (15:15)',
            'Generate Schedule' => 'Создать расписание',
            'Recent Schedules' => 'Недавние расписания',
            'No Groups Available' => 'Нет доступных групп',
            'Create groups in System Settings first.' => 'Сначала создайте группы в Настройках системы.',
            'Ready to Generate' => 'Готово к созданию',
            'Select a group and configure schedule options, then click "Generate Schedule" to create an AI-powered schedule.' => 'Выберите группу и настройте параметры расписания, затем нажмите "Создать расписание", чтобы создать расписание с помощью ИИ.',
            
            // Class periods
            'Class Periods:' => 'Периоды занятий:',
            '1: 08:00-09:30' => '1: 08:00-09:30',
            '2: 09:45-11:15' => '2: 09:45-11:15',
            '3: 11:30-13:00' => '3: 11:30-13:00',
            '4: 13:30-15:00' => '4: 13:30-15:00',
            '5: 15:15-16:45' => '5: 15:15-16:45',
            
            // Buttons
            'Print Schedule' => 'Распечатать расписание',
            'Copy' => 'Копировать',
            
            // Generated schedule content
            'week(s)' => 'неделя(и)',
            'classes' => 'занятия',
            'hours' => 'часы',
            'Summary' => 'Сводка',
            
            // Error messages
            'Please select a group.' => 'Пожалуйста, выберите группу.',
            'Group not found.' => 'Группа не найдена.',
            'Failed to parse schedule data. Please try again.' => 'Не удалось проанализировать данные расписания. Пожалуйста, попробуйте снова.',
            'Failed to generate schedule. Please try again.' => 'Не удалось создать расписание. Пожалуйста, попробуйте снова.',
            'Error connecting to AI service. Please try again later.' => 'Ошибка подключения к сервису ИИ. Пожалуйста, повторите попытку позже.',
            
            // My Grades page
            'Overall GPA' => 'Общий средний балл',
            'Recent Grades' => 'Недавние оценки',
            'Grades by Course' => 'Оценки по курсам',
            'AI Test History' => 'История тестов ИИ',
            'Practice Recommendations' => 'Рекомендации для практики',
            'Topics to Review' => 'Темы для повторения',
            'Performance Analysis' => 'Анализ успеваемости',
            'Average Score' => 'Средний балл',
            'Tests Taken' => 'Пройденные тесты',
            'Accuracy Rate' => 'Процент точности',
            
            // Classes page
            'My Groups' => 'Мои группы',
            'Create Topic' => 'Создать тему',
            'Class Topics' => 'Темы занятий',
            'Topic Title' => 'Название темы',
            'Description' => 'Описание',
            'Due Date' => 'Срок сдачи',
            'Test Difficulty' => 'Сложность теста',
            'Easy - Basic understanding' => 'Легко - Базовое понимание',
            'Medium - Standard level' => 'Средне - Стандартный уровень',
            'Hard - Advanced concepts' => 'Сложно - Продвинутые концепции',
            'Select Groups' => 'Выбрать группы',
            'Topic successfully created.' => 'Тема успешно создана.',
            'Error creating topic.' => 'Ошибка при создании темы.',
            
            // Grade management
            'Grade Management' => 'Управление оценками',
            'Select Group' => 'Выбрать группу',
            'Select Subject' => 'Выбрать предмет',
            'Students' => 'Студенты',
            'Grade' => 'Оценка',
            'Save Grades' => 'Сохранить оценки',
            
            // AI Tests
            'Select Topic' => 'Выбрать тему',
            'Generate Test' => 'Создать тест',
            'Start Test' => 'Начать тест',
            'Time Remaining' => 'Оставшееся время',
            'Question' => 'Вопрос',
            'of' => 'из',
            'Submit Test' => 'Отправить тест',
            'Test Results' => 'Результаты теста',
            'Your Score' => 'Ваш балл',
            'Correct Answers' => 'Правильные ответы',
            'Incorrect Answers' => 'Неправильные ответы',
            'Analysis' => 'Анализ',
            'Recommendations' => 'Рекомендации',
            
            // Admin panel
            'Approve' => 'Одобрить',
            'Reject' => 'Отклонить',
            'Username' => 'Имя пользователя',
            'Email' => 'Электронная почта',
            'Role' => 'Роль',
            'Status' => 'Статус',
            'Action' => 'Действие',
            'Pending Registrations' => 'Ожидающие регистрации',
            
            // Teacher management
            'Add Teacher' => 'Добавить преподавателя',
            'Edit Teacher' => 'Редактировать преподавателя',
            'Remove' => 'Удалить',
            'Assign Subjects' => 'Назначить предметы',
            'Assigned Groups' => 'Назначенные группы',
            'Select Subjects' => 'Выбрать предметы',
            'Select Teachers' => 'Выбрать преподавателей',
            
            // Student management
            'Add Student' => 'Добавить студента',
            'Edit Student' => 'Редактировать студента',
            'Enroll in Group' => 'Записать в группу',
            'Unassign' => 'Отменить назначение',
            'Enrolled Groups' => 'Записанные группы',
            'Available Groups' => 'Доступные группы',
            
            // System settings
            'Add Subject' => 'Добавить предмет',
            'Add Course' => 'Добавить курс',
            'Subject Name' => 'Название предмета',
            'Course Name' => 'Название курса',
            'Course Code' => 'Код курса',
            'Credits' => 'Кредиты',
            'Subject successfully added.' => 'Предмет успешно добавлен.',
            'Course successfully added.' => 'Курс успешно добавлен.',
            
            // Login/Register
            'Login' => 'Вход',
            'Register' => 'Регистрация',
            'First Name' => 'Имя',
            'Last Name' => 'Фамилия',
            'Password' => 'Пароль',
            'Confirm Password' => 'Подтвердите пароль',
            'Already have an account?' => 'Уже есть аккаунт?',
            'Don\'t have an account?' => 'Нет аккаунта?',
            'Forgot Password?' => 'Забыли пароль?',
            'Sign In' => 'Войти',
            'Sign Up' => 'Зарегистрироваться',
            
            // Logout
            'Logout' => 'Выход',
            
            // General terms
            'Dashboard' => 'Панель управления',
            'Profile' => 'Профиль',
            'Settings' => 'Настройки',
            'Help' => 'Помощь',
            'About' => 'О программе',
            'Version' => 'Версия',
            
            // Months and days
            'Monday' => 'Понедельник',
            'Tuesday' => 'Вторник',
            'Wednesday' => 'Среда',
            'Thursday' => 'Четверг',
            'Friday' => 'Пятница',
            'Saturday' => 'Суббота',
            'Sunday' => 'Воскресенье',
            
            'January' => 'Январь',
            'February' => 'Февраль',
            'March' => 'Март',
            'April' => 'Апрель',
            'May' => 'Май',
            'June' => 'Июнь',
            'July' => 'Июль',
            'August' => 'Август',
            'September' => 'Сентябрь',
            'October' => 'Октябрь',
            'November' => 'Ноябрь',
            'December' => 'Декабрь',
            
            // Other
            'Powered by GPT-4' => 'Работает на GPT-4',
            'Loading...' => 'Загрузка...',
            'Generating Schedule...' => 'Создание расписания...',
            'AI is creating the schedule...' => 'ИИ создает расписание...',
            
            // Academic Process
            'Performance Graphs' => 'Графики успеваемости',
            'Grade Distribution' => 'Распределение оценок',
            'Subject Performance' => 'Успеваемость по предметам',
            'Trend Analysis' => 'Анализ тенденций',
            
            // Knowledge Base
            'Search Resources' => 'Поиск ресурсов',
            'Categories' => 'Категории',
            'Documents' => 'Документы',
            'Videos' => 'Видео',
            'Articles' => 'Статьи',
            'Resources' => 'Ресурсы',
            
            // Homework
            'Assignment' => 'Задание',
            'Assigned Date' => 'Дата назначения',
            'Submission Date' => 'Дата сдачи',
            'Submitted' => 'Сдано',
            'Not Submitted' => 'Не сдано',
            'Late Submission' => 'Поздняя сдача',
            
            // Journals
            'Class Journal' => 'Журнал занятий',
            'Date' => 'Дата',
            'Topic Covered' => 'Рассмотренная тема',
            'Attendance' => 'Посещаемость',
            'Notes' => 'Заметки',
            
            // Statistics
            'Performance Metrics' => 'Метрики успеваемости',
            'Progress Tracking' => 'Отслеживание прогресса',
            'Comparative Analysis' => 'Сравнительный анализ',
            
            // Deadlines
            'Upcoming Deadlines' => 'Предстоящие сроки',
            'Overdue' => 'Просрочено',
            'Completed' => 'Завершено',
            
            // Messages
            'Welcome back!' => 'С возвращением!',
            'You have successfully logged in.' => 'Вы успешно вошли в систему.',
            'You have been logged out.' => 'Вы вышли из системы.',
            'Operation successful.' => 'Операция выполнена успешно.',
            'An error occurred.' => 'Произошла ошибка.'
        ]
    ];
    
    // Return the translated text if available, otherwise return the original
    return $translations[$lang][$key] ?? $key;
}

// Helper function to translate with variables
function t($key, $lang = 'ru', $vars = []) {
    $translation = getTranslation($key, $lang);
    
    // Replace variables in the translation if provided
    foreach ($vars as $var => $value) {
        $translation = str_replace('{{' . $var . '}}', $value, $translation);
    }
    
    return $translation;
}