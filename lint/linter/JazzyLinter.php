<?php
/*
 Copyright 2016-present Google Inc. All Rights Reserved.

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

 http://www.apache.org/licenses/LICENSE-2.0

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.
 */

/**
 * Uses jazzy to enforce documentation on Objective-C/Swift code.
 *
 * This linter requires that a .jazzy.yaml file containing the relevant configuration settings for
 * your source lives somewhere on disk. This file must live alongside your source or in a parent
 * directory.
 */
final class JazzyLinter extends ArcanistLinter {

  const LINT_MISSING_DOCUMENTATION = 1;

  // The cached report for a given .jazzy.yaml.
  // $lintReports[.jazzy.yaml path] = report
  private $lintReports = array();

  // We cache the swift binary version.
  private $swiftVersion;

  public function getInfoName() {
    return pht('Jazzy Documentation Linter');
  }

  public function getInfoURI() {
    return 'http://github.com/realm/jazzy';
  }

  public function getInfoDescription() {
    return pht('Use jazzy to identify missing documentation for Objective-C/Swift code.');
  }

  public function getLinterName() {
    return 'JAZZY';
  }

  public function getLinterConfigurationName() {
    return 'jazzy';
  }

  public function getDefaultBinary() {
    return 'jazzy';
  }

  public function getInstallInstructions() {
    return pht('Install jazzy with `gem install jazzy`');
  }

  public function shouldExpectCommandErrors() {
    return false;
  }

  protected function getMandatoryFlags() {
    return array(
      "--skip-documentation"
    );
  }

  public function getLintSeverityMap() {
    return array(
      self::LINT_MISSING_DOCUMENTATION => ArcanistLintSeverity::SEVERITY_WARNING
    );
  }

  public function getLintNameMap() {
    return array(
      self::LINT_MISSING_DOCUMENTATION => pht('Missing documentation'),
    );
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = array();
    $regex = '/(?P<version>\d+\.\d+\.\d+)/';
    if (preg_match($regex, $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  private function getJazzyConfigPathForPath($path) {
    $root = $this->getProjectRoot();
    
    $variations = array(
      '.jazzy.yaml',
      '.jazzy.yml'
    );

    $jazzyDir = $path;
    while (!empty($jazzyDir) && $jazzyDir != '.') {
      $jazzyDir = dirname($jazzyDir);
      $path = Filesystem::resolvePath($jazzyDir, $root);
      foreach ($variations as $variation) {
        $configPath = Filesystem::resolvePath($variation, $path);
        if (file_exists($configPath)) {
          return $configPath;
        }
      }
    }
    return false;
  }
  
  private function getSwiftVersion() {
    if (isset($this->swiftVersion)) {
      return $this->swiftVersion;
    }
    list($stdout) = execx('swift --version');

    $matches = array();
    $regex = '/version (?P<version>\d+\.\d+(?:\.\d)?+)/';
    if (preg_match($regex, $stdout, $matches)) {
      $this->swiftVersion = $matches['version'];
    } else {
      $this->swiftVersion = false;
    }
    return $this->swiftVersion;
  }
  
  private function reportForPath($path) {
    $jazzyConfigPath = $this->getJazzyConfigPathForPath($path);
    if (!$jazzyConfigPath) {
      return false;
    }

    // Return previously-calculated report for this path.
    if (array_key_exists($jazzyConfigPath, $this->lintReports)) {
      return $this->lintReports[$jazzyConfigPath];
    }

    $reports = array();
    $this->lintReports[$jazzyConfigPath] = $reports;

    // Create temp directory to store the report.
    $future = new ExecFuture('mktemp -dt $(basename $(dirname "%C"))', $jazzyConfigPath);
    list($tmperror, $tmp_stdout, $tmp_stderr) = $future->resolve();
    if ($tmperror != 0) {
      // TODO: Return some form of error.
      return false;
    }

    $tmpdir = trim($tmp_stdout);

    // Generate the report.
    $future = new ExecFuture('%C --skip-documentation --output="%C" --config="%C" --swift-version="%C"',
      $this->getDefaultBinary(),
      $tmpdir,
      $jazzyConfigPath,
      $this->getSwiftVersion()
    );
    list($jazzyerror, $jazzy_stdout, $jazzy_stderr) = $future->resolve();

    if ($jazzyerror != 0) {
      // TODO: Return some form of error.
      return false;
    }

    $undocumented = file_get_contents("$tmpdir/undocumented.json");
    if (!$undocumented) {
      // TODO: Return some form of error.
      return false;
    }

    $report = json_decode($undocumented, TRUE);
    if (!$report) {
      // TODO: Return some form of error.
      return false;
    }

    $this->lintReports[$jazzyConfigPath] = $report;

    return $this->lintReports[$jazzyConfigPath];
  }

  public function lintPath($path) {
    $report = $this->reportForPath($path);

    if (!$report) {
      return;
    }

    $messages = array();
    foreach ($report['warnings'] as $warning) {
      if ($warning['warning'] != 'undocumented') {
        continue; // Ignore other lint-report types.
      }

      // TODO: Pre-sort this list into a map of path to warnings.
      $strippedPath = str_replace($this->getProjectRoot().'/', '', $warning['file']);

      if ($strippedPath != $path) {
        continue;
      }

      $this->raiseLintAtLine(
        intval($warning['line']),
        1,
        self::LINT_MISSING_DOCUMENTATION,
        $warning['symbol']." is missing documentation.\nPlease use `/** */` blocks to document APIs.");
    }
  }
}

?>
