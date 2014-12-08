# uLogin

Donate link: http://ulogin.ru  
Tags: ulogin, login, social, authorization  
Tested up to: 1.10.5  
Stable tag: 2.0.0  
License: GPLv2  

**uLogin** — это инструмент, который позволяет пользователям получить единый доступ к различным Интернет-сервисам без необходимости повторной регистрации,
а владельцам сайтов — получить дополнительный приток клиентов из социальных сетей и популярных порталов (Google, Яндекс, Mail.ru, ВКонтакте, Facebook и др.)


## Установка

- скопируйте файлы в корень сайта.
- В админке перейдите в раздел *"Компоненты"* и нажмите кнопку *"Установить компоненты"*.

При успешной установке компонента виджеты uLogin автоматически устанавливаются и готовы к работе.  
Описание для более детальной настройки виджетов находится ниже.  


## Страница настроек компонента

Вы можете создать свой виджет uLogin и редактировать его самостоятельно:

для создания виджета необходимо зайти в Личный Кабинет (ЛК) на сайте http://ulogin.ru/lk.php,
добавить свой сайт к списку Мои сайты и на вкладке Виджеты добавить новый виджет. После этого вы можете отредактировать свой виджет.

**Важно!** Для успешной работы плагина необходимо включить в обязательных полях профиля поле **Еmail** в Личном кабинете uLogin.

На своём сайте в разделе *"Компоненты"* в настройках uLogin укажите значение поля **uLogin ID**.

Здесь же вы можете указать группу для новых, регистрирующихся через uLogin, пользователей.  
По умолчанию установлена группа *"Пользователи"*.  

При установке компонента также автоматически создаётся группа пользователей *"Пользователи uLogin"*.   
Для этой группы права доступа устанавливаются те же, что и у группы *"Пользователи"*.  
Вы можете установить эту группу для новых пользователей, регистрирующихся через виджет uLogin, 
 но не забывайте при этом настроить права доступа для этой группы в используемых вами модулях и других дополнениях 
 (в частности, модуль *"Меню пользователя"* не будет отображаться для данной группы) 



## Виджеты компонента 

Пакет дополнения включает в себя 3 модуля:

- *Войти с помощью* - обеспечивает вход/регистрацию пользователей через популярные социальные сети и порталы;
- *Мои аккаунты* - позволяет пользователю просматривать подключенные аккаунты соцсетей, добавлять новые;
- *Сообщения uLogin* - обеспечивает вывод сообщений.

Данные модули устанавливаются автоматически вместе с компонентом.

Модули *"Войти с помощью"* и *"Мои аккаунты"* также могут иметь своё значение **uLogin ID**, отличное от настроек в компонентах.
