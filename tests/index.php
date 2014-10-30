<div style="background-color: yellow;color: red;"><?php if (isset($_GET['msg'])) echo 'Your project was saved'; ?></div>
<form method="POST" action="create_project.php">
    <input type="text" placeholder="bot-name" name="botName">
    <select name="atOnce">
        <option value="0">links at once</option>
        <OPTION VALUE="3">3</OPTION>
        <OPTION VALUE="5">5</OPTION>
        <OPTION VALUE="10">10</OPTION>
    </select>
    <select name="maxDepth">
        <option value="0">depth</option>
        <OPTION VALUE="1">1</OPTION>
        <OPTION VALUE="2">2</OPTION>
        <OPTION VALUE="3">3</OPTION>
        <OPTION VALUE="4">4</OPTION>
        <OPTION VALUE="5">5</OPTION>
    </select>
    <input type="text" placeholder="project's title" name="project_title">
    <input type="text" placeholder="main url" name="url">
    <input type="submit" value="ADD">
</form>