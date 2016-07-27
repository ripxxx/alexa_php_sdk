How to start development
------------------------

Setup everything here:
https://developer.amazon.com/public/solutions/alexa/alexa-voice-service/docs/java-client-sample


```
cp base/config/main.php.default base/config/main.php 
# create your version of config
mkdir project      # create directory for project this directory should be your root of your webserver
cd project

mkdir alexa_skills # create directory for skills
mkdir users        # create directory for user sessions

git clone https://github.com/ripxxx/alexa_php_sdk.git

vi|nano|notepad alexa_php_sdk-master/base/config/main.php 
# edit path for skills and users (should point on created above directories)
```

1. Open list of skills:
https://developer.amazon.com/edw/home.html#/skills/list

2. "Add New". Set Custom Interaction Model. Fill in Name, Invocation Name.

3. Fill in Intent Schema, Custom Slot Types, Sample Utterances with content from assets in your skill